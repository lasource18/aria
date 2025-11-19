# ADR-0004: Database Schema and Migrations Strategy

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, backend, database, postgresql, performance]

## Context and Problem Statement

Aria's database must support high-throughput ticket sales (100+ orders/minute during popular event on-sales), geospatial search for events near attendees, full-text search for event discovery, and audit logging for compliance. The schema must balance:

- **Performance**: P95 latency <200ms for core read APIs (event search, order lookup)
- **Scalability**: Handle 1M+ tickets/year with minimal query degradation
- **Data integrity**: Prevent double-booking, maintain inventory accuracy during concurrent checkouts
- **Compliance**: Audit trail for all state transitions (orders, payments, payouts)
- **Extensibility**: Support future features (seating, recurring events, coupons) without schema rewrites

**Referenced sections**: DESIGN.md Section 5 (Data Model ERD), Section 6 (State Machines)

## Decision Drivers

- **Regional latency**: Single-region PostgreSQL (EU-West or Abidjan-proximate) for <200ms P95
- **Concurrency**: Multiple users buying last tickets simultaneously → optimistic locking required
- **Search requirements**: Full-text (pg_trgm), geospatial (PostGIS), date-range filtering
- **Audit compliance**: Immutable logs for payment state changes, admin actions, refunds
- **Cost efficiency**: Managed PostgreSQL (DigitalOcean, AWS RDS) vs self-hosted Postgres on Kubernetes
- **Migration safety**: Zero-downtime schema changes in production

## Considered Options

### Option A: PostgreSQL 16 with Extensions (PostGIS, pg_trgm, pgcrypto)
Single PostgreSQL instance with extensions for geospatial queries, full-text search, and PII encryption.

**Pros**:
- Single database reduces operational complexity
- ACID guarantees prevent inventory double-booking
- PostGIS handles geospatial radius queries efficiently
- pg_trgm enables fuzzy search for event titles/venues
- Battle-tested migrations via Laravel

**Cons**:
- Single point of failure (mitigated by managed service with replicas)
- No horizontal scaling (vertical scaling sufficient for MVP)

### Option B: PostgreSQL + Elasticsearch for Search
Use PostgreSQL for transactional data; sync to Elasticsearch for advanced search and analytics.

**Pros**:
- Elasticsearch excels at full-text search with relevance scoring
- Can add filters (price range, category) without complex SQL

**Cons**:
- Added operational complexity (sync lag, index rebuilding)
- Cost increase (Elasticsearch cluster + PostgreSQL)
- Overkill for MVP scope (10K events expected in first year)

### Option C: PostgreSQL + ClickHouse for Analytics
Keep PostgreSQL for OLTP; ETL to ClickHouse for organizer dashboards and reporting.

**Pros**:
- ClickHouse optimized for aggregation queries (sales by date, revenue by ticket type)
- Reduces load on primary database

**Cons**:
- Premature optimization (PostgreSQL materialized views sufficient for MVP)
- Adds ETL pipeline complexity
- Deferred to post-MVP when analytics queries become bottleneck

## Decision Outcome

**Chosen option**: Option A - PostgreSQL 16 with Extensions

**Justification**: PostgreSQL 16 with PostGIS, pg_trgm, and pgcrypto meets all MVP requirements without operational overhead of additional datastores. Managed PostgreSQL services (DigitalOcean Managed Database, AWS RDS) provide automated backups, read replicas, and vertical scaling. If search becomes a bottleneck post-MVP, we can add Elasticsearch without schema rewrites.

## Implementation Details

### Core Schema (Aligned with DESIGN.md Section 5)

```sql
-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "postgis";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Users
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email TEXT UNIQUE NOT NULL,
    phone TEXT,  -- E.164 format, encrypted at rest
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    preferences JSONB DEFAULT '{}',
    locale TEXT DEFAULT 'fr-FR',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Organizations
CREATE TABLE orgs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    country_code TEXT DEFAULT 'CI',
    kyb_data JSONB,  -- KYC/KYB verification documents
    payout_channel TEXT CHECK (payout_channel IN ('orange_mo', 'mtn_momo', 'wave', 'bank')),
    payout_identifier TEXT,  -- MSISDN or IBAN, encrypted
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Organization members (multi-tenant RBAC)
CREATE TABLE org_members (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    org_id UUID NOT NULL REFERENCES orgs(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role TEXT NOT NULL CHECK (role IN ('owner', 'admin', 'staff', 'finance')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(org_id, user_id)
);

-- Events
CREATE TABLE events (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    org_id UUID NOT NULL REFERENCES orgs(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    description_md TEXT,
    category TEXT,
    venue_name TEXT,
    venue_address TEXT,
    location GEOGRAPHY(POINT, 4326),  -- PostGIS: lat/lon for radius search
    start_at TIMESTAMPTZ NOT NULL,
    end_at TIMESTAMPTZ,
    timezone TEXT DEFAULT 'Africa/Abidjan',
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'canceled', 'ended')),
    is_online BOOLEAN DEFAULT FALSE,
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Ticket types
CREATE TABLE ticket_types (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    event_id UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    type TEXT NOT NULL CHECK (type IN ('free', 'paid', 'donation')),
    price_xof NUMERIC(10, 2) DEFAULT 0,
    fee_pass_through_pct NUMERIC(5, 2) DEFAULT 0,  -- % of fee buyer pays
    max_qty INTEGER,
    per_order_limit INTEGER DEFAULT 10,
    sales_start TIMESTAMPTZ,
    sales_end TIMESTAMPTZ,
    refundable BOOLEAN DEFAULT TRUE,
    sold_count INTEGER DEFAULT 0,  -- Denormalized for quick inventory check
    reserved_count INTEGER DEFAULT 0,  -- Pending orders holding inventory
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Orders
CREATE TABLE orders (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    event_id UUID NOT NULL REFERENCES events(id),
    buyer_user_id UUID REFERENCES users(id),
    email TEXT NOT NULL,
    phone TEXT NOT NULL,  -- Encrypted
    currency TEXT DEFAULT 'XOF',
    state TEXT DEFAULT 'created' CHECK (state IN ('created', 'awaiting_payment', 'paid', 'canceled', 'expired', 'refunded')),
    amount_subtotal NUMERIC(10, 2) NOT NULL,
    amount_fees NUMERIC(10, 2) DEFAULT 0,
    amount_total NUMERIC(10, 2) NOT NULL,
    checkout_channel TEXT CHECK (checkout_channel IN ('web', 'pwa', 'mobile', 'admin')),
    payment_provider TEXT,
    idempotency_key UUID UNIQUE NOT NULL DEFAULT uuid_generate_v4(),
    expires_at TIMESTAMPTZ,  -- NULL if paid
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    version INTEGER DEFAULT 1  -- Optimistic locking
);

-- Order items
CREATE TABLE order_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    ticket_type_id UUID NOT NULL REFERENCES ticket_types(id),
    qty INTEGER NOT NULL CHECK (qty > 0),
    unit_price_xof NUMERIC(10, 2) NOT NULL,
    line_total NUMERIC(10, 2) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Payments
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    order_id UUID NOT NULL REFERENCES orders(id),
    provider TEXT NOT NULL CHECK (provider IN ('orange_mo', 'mtn_momo', 'wave', 'stripe')),
    provider_ref TEXT UNIQUE,  -- Provider's transaction ID
    state TEXT DEFAULT 'initiated' CHECK (state IN ('initiated', 'pending', 'succeeded', 'failed', 'canceled')),
    request_payload JSONB,
    webhook_payload JSONB,
    idempotency_key UUID UNIQUE NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Tickets
CREATE TABLE tickets (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    order_id UUID NOT NULL REFERENCES orders(id),
    ticket_type_id UUID NOT NULL REFERENCES ticket_types(id),
    owner_user_id UUID REFERENCES users(id),
    code TEXT UNIQUE NOT NULL,  -- Base32 alphanumeric (e.g., "A3B7K2M9")
    qr_url TEXT,  -- S3 URL to QR code image
    qr_signature TEXT,  -- HMAC for offline verification
    state TEXT DEFAULT 'issued' CHECK (state IN ('issued', 'transferred', 'checked_in', 'void')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Check-in logs (immutable audit trail)
CREATE TABLE checkin_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    event_id UUID NOT NULL REFERENCES events(id),
    ticket_id UUID NOT NULL REFERENCES tickets(id),
    staff_user_id UUID REFERENCES users(id),
    action TEXT NOT NULL CHECK (action IN ('check_in', 'undo')),
    device_id TEXT,  -- Offline device fingerprint
    occurred_at TIMESTAMPTZ DEFAULT NOW()
);

-- Payouts
CREATE TABLE payouts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    org_id UUID NOT NULL REFERENCES orgs(id),
    method TEXT NOT NULL CHECK (method IN ('orange_mo', 'mtn_momo', 'wave', 'bank')),
    destination TEXT NOT NULL,  -- MSISDN or IBAN, encrypted
    amount_xof NUMERIC(10, 2) NOT NULL,
    state TEXT DEFAULT 'queued' CHECK (state IN ('queued', 'processing', 'sent', 'failed')),
    provider_ref TEXT,
    scheduled_for TIMESTAMPTZ NOT NULL,
    processed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Audit logs (admin actions, state transitions)
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    entity_type TEXT NOT NULL,  -- 'order', 'payment', 'payout', 'org'
    entity_id UUID NOT NULL,
    action TEXT NOT NULL,  -- 'state_transition', 'refund_initiated', 'payout_approved'
    actor_user_id UUID REFERENCES users(id),
    old_state JSONB,
    new_state JSONB,
    reason TEXT,
    occurred_at TIMESTAMPTZ DEFAULT NOW()
);
```

### Indexing Strategy (Performance Optimization)

```sql
-- Users
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_phone ON users(phone);  -- Encrypted column, hash index

-- Events (full-text + geospatial)
CREATE INDEX idx_events_org_id ON events(org_id);
CREATE INDEX idx_events_slug ON events(slug);
CREATE INDEX idx_events_status ON events(status) WHERE status = 'published';  -- Partial index
CREATE INDEX idx_events_start_at ON events(start_at);
CREATE INDEX idx_events_title_trgm ON events USING gin(title gin_trgm_ops);  -- Fuzzy search
CREATE INDEX idx_events_venue_trgm ON events USING gin(venue_name gin_trgm_ops);
CREATE INDEX idx_events_location ON events USING gist(location);  -- Geospatial radius queries

-- Ticket types
CREATE INDEX idx_ticket_types_event_id ON ticket_types(event_id);

-- Orders
CREATE INDEX idx_orders_event_id ON orders(event_id);
CREATE INDEX idx_orders_buyer_user_id ON orders(buyer_user_id);
CREATE INDEX idx_orders_state ON orders(state);
CREATE INDEX idx_orders_expires_at ON orders(expires_at) WHERE state = 'awaiting_payment';  -- Expiry sweeper
CREATE INDEX idx_orders_idempotency_key ON orders(idempotency_key);

-- Payments
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_payments_provider_ref ON payments(provider_ref);
CREATE INDEX idx_payments_state ON payments(state) WHERE state IN ('pending', 'initiated');  -- Reconciliation

-- Tickets
CREATE INDEX idx_tickets_code ON tickets(code);  -- Check-in lookup
CREATE INDEX idx_tickets_order_id ON tickets(order_id);
CREATE INDEX idx_tickets_owner_user_id ON tickets(owner_user_id);

-- Check-in logs
CREATE INDEX idx_checkin_logs_event_id ON checkin_logs(event_id);
CREATE INDEX idx_checkin_logs_ticket_id ON checkin_logs(ticket_id);
CREATE INDEX idx_checkin_logs_occurred_at ON checkin_logs(occurred_at);

-- Payouts
CREATE INDEX idx_payouts_org_id ON payouts(org_id);
CREATE INDEX idx_payouts_state ON payouts(state);
CREATE INDEX idx_payouts_scheduled_for ON payouts(scheduled_for);

-- Audit logs
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_logs_occurred_at ON audit_logs(occurred_at);
```

### Partitioning Strategy (High-Volume Tables)

**Deferred to post-MVP** but designed for:

```sql
-- Partition orders by created_at (quarterly)
CREATE TABLE orders_2025_q1 PARTITION OF orders
    FOR VALUES FROM ('2025-01-01') TO ('2025-04-01');

-- Partition checkin_logs by occurred_at (monthly)
CREATE TABLE checkin_logs_2025_11 PARTITION OF checkin_logs
    FOR VALUES FROM ('2025-11-01') TO ('2025-12-01');
```

**Trigger**: When `orders` table exceeds 10M rows or query performance degrades

### Migration Tooling (Laravel Migrations)

**Zero-Downtime Migration Pattern**:

```php
<?php
// database/migrations/2025_11_19_000001_create_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('orgs')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description_md')->nullable();
            $table->string('category')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->geography('location', 'point', 4326)->nullable();  // PostGIS
            $table->timestampTz('start_at');
            $table->timestampTz('end_at')->nullable();
            $table->string('timezone')->default('Africa/Abidjan');
            $table->enum('status', ['draft', 'published', 'canceled', 'ended'])->default('draft');
            $table->boolean('is_online')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();

            // Indexes
            $table->index('org_id');
            $table->index('start_at');
            $table->index(['status'], 'idx_events_status_published')->where('status', 'published');
        });

        // Add trigram indexes via raw SQL (not supported by Blueprint)
        DB::statement('CREATE INDEX idx_events_title_trgm ON events USING gin(title gin_trgm_ops)');
        DB::statement('CREATE INDEX idx_events_location ON events USING gist(location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

**Adding Column Without Downtime**:

```php
<?php
// Add column with default (PostgreSQL 11+ allows instant ADD COLUMN with default)
Schema::table('events', function (Blueprint $table) {
    $table->boolean('featured')->default(false);
});

// Backfill in batches (avoid table lock)
DB::table('events')
    ->whereNull('featured')
    ->chunkById(1000, function ($events) {
        DB::table('events')
            ->whereIn('id', $events->pluck('id'))
            ->update(['featured' => false]);
    });

// Make NOT NULL after backfill
Schema::table('events', function (Blueprint $table) {
    $table->boolean('featured')->default(false)->nullable(false)->change();
});
```

### Seed Data (Development and Staging)

```php
<?php
// database/seeders/DevelopmentSeeder.php

namespace Database\Seeders;

use App\Models\{User, Org, Event, TicketType};
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Create test organizer
        $user = User::factory()->create([
            'email' => 'organizer@aria.ci',
            'full_name' => 'Test Organizer',
        ]);

        $org = Org::factory()->create([
            'name' => 'Acme Events',
            'payout_channel' => 'orange_mo',
            'verified' => true,
        ]);

        $org->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Create sample events
        $event = Event::factory()->create([
            'org_id' => $org->id,
            'title' => 'Abidjan Music Festival 2025',
            'slug' => 'abidjan-music-fest-2025',
            'status' => 'published',
            'start_at' => now()->addDays(30),
            'venue_name' => 'Palais de la Culture',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-4.0083, 5.3599), 4326)"),  // Abidjan coords
        ]);

        // Create ticket types
        TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'type' => 'paid',
            'price_xof' => 5000,
            'max_qty' => 1000,
        ]);

        TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'type' => 'paid',
            'price_xof' => 15000,
            'max_qty' => 100,
        ]);
    }
}
```

### Backup and Disaster Recovery

**Managed PostgreSQL (Recommended)**:
- DigitalOcean Managed Database: Daily automated backups (7-day retention), point-in-time recovery
- AWS RDS: Automated snapshots, read replicas for failover

**Backup Schedule**:
- **Full backup**: Daily at 02:00 UTC (managed service handles this)
- **WAL archiving**: Continuous (for point-in-time recovery)
- **Replica lag**: <30s for read replica in same region

**Recovery Testing**:
- Monthly restore drill to staging environment
- Document RTO (Recovery Time Objective): 1 hour
- Document RPO (Recovery Point Objective): 5 minutes (based on WAL archiving)

### Database Configuration (PostgreSQL 16)

```conf
# postgresql.conf (Tuned for 4 vCPU, 16GB RAM instance)

# Connections
max_connections = 200
superuser_reserved_connections = 3

# Memory
shared_buffers = 4GB
effective_cache_size = 12GB
maintenance_work_mem = 1GB
work_mem = 20MB

# Checkpoints
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100

# Query planner
random_page_cost = 1.1  # SSD storage
effective_io_concurrency = 200

# Logging
log_statement = 'mod'  # Log all DDL/DML
log_duration = on
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
log_min_duration_statement = 1000  # Log queries > 1s

# Replication (for read replicas)
wal_level = replica
max_wal_senders = 10
max_replication_slots = 10
```

## Consequences

### Positive
- **Single source of truth**: PostgreSQL handles transactional and search workloads without sync lag
- **Data integrity**: ACID guarantees prevent inventory double-booking and payment duplication
- **Performance**: Indexes and partial indexes optimize hot queries (<100ms P95 for event search)
- **Extensibility**: JSONB columns allow schema evolution without migrations (settings, metadata)
- **Compliance**: Audit logs and immutable check-in logs satisfy regulatory requirements

### Negative
- **Vertical scaling limit**: PostgreSQL doesn't horizontally shard (acceptable for MVP; 10M orders/year fits single instance)
- **Extension dependency**: PostGIS and pg_trgm require managed service support (most providers support these)
- **Migration complexity**: Zero-downtime migrations require careful planning (column additions with defaults)

### Risks and Mitigations

**Risk**: Database becomes bottleneck during ticket on-sale spikes
**Mitigation**: Read replicas for search queries; connection pooling (PgBouncer); Redis cache for hot event pages

**Risk**: Accidental schema change breaks production
**Mitigation**: Require peer review for migrations; test migrations in staging with production-like data volume; use shadow deployments

**Risk**: Data loss due to operator error (DROP TABLE)
**Mitigation**: Restrict `DROP` permissions; require confirmation prompts in CLI; maintain 30-day backup retention

## References
- DESIGN.md Section 5: Data Model (ERD + Core Tables)
- DESIGN.md Section 10: Search & Discovery (Full-Text + Geospatial)
- Product Spec: aria-mvp.md Non-Functional Requirements (Performance)
- External: [PostgreSQL 16 Docs](https://www.postgresql.org/docs/16/), [PostGIS](https://postgis.net/), [Laravel Migrations](https://laravel.com/docs/11.x/migrations)
