# ADR-0003: Monorepo Structure and Platform Architecture

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, monorepo, backend, frontend, mobile, infra]

## Context and Problem Statement

Aria is a multi-platform ticketing system with distinct client applications (mobile app, public-facing website, organizer dashboard) all consuming a shared backend API. The codebase must support:

- **Backend API**: Laravel 11 + PHP 8.3 serving REST endpoints
- **Organizer Dashboard**: Inertia.js + TypeScript + React for SSR admin interface
- **Public Website**: Next.js for SEO-optimized event discovery and ticket purchasing
- **Mobile App**: Expo React Native for attendee and organizer mobile experiences

Without a clear monorepo structure, we risk:
- Code duplication (shared types, utilities, UI components)
- Inconsistent API contracts between frontend and backend
- Complex deployment pipelines with multiple repositories
- Difficult cross-platform refactoring and versioning

**Referenced sections**: DESIGN.md Section 4 (High-Level Architecture), Section 16 (Tech Stack Choices), Section 27 (Mobile App Alignment)

## Decision Drivers

- **Code sharing**: Reuse TypeScript types, UI components, and utilities across web and mobile
- **Build speed**: Developers should only rebuild affected packages, not entire codebase
- **Deployment independence**: Backend, web, and mobile should deploy independently
- **Cloudflare Pages compatibility**: Public website must use edge-safe APIs and Next.js static export
- **Team autonomy**: Backend and frontend engineers should work in parallel without conflicts
- **Type safety**: API contracts shared via generated TypeScript types from Laravel models

## Considered Options

### Option A: Multi-Repo (Separate Repositories)
Maintain separate repositories for `aria-api`, `aria-web`, `aria-mobile`, `aria-dashboard`.

**Pros**:
- Clear separation of concerns
- Independent versioning and CI/CD pipelines
- Smaller repository clones for focused work

**Cons**:
- Code duplication (types, utilities, UI components)
- Difficult to refactor shared interfaces atomically
- Version skew between repos (mobile using v1.2 types, API on v1.3)
- Complex dependency management (publishing shared packages to npm)

### Option B: Monorepo with Turborepo (Recommended)
Single repository with `apps/` and `packages/` directories; use Turborepo for build orchestration.

**Pros**:
- Atomic commits across API and frontend ensure type safety
- Shared packages (types, UI, utils) versioned together
- Turborepo caching speeds up builds (only rebuild changed packages)
- Single CI/CD pipeline with parallel deployment
- Easier code review (see API and frontend changes together)

**Cons**:
- Larger repository size (all code in one place)
- Requires understanding of workspace dependencies
- Initial setup complexity (Turborepo config, shared tsconfig)

### Option C: Monorepo with Nx
Similar to Turborepo but uses Nx for task orchestration and code generation.

**Pros**:
- Advanced dependency graph visualization
- Code generators for scaffolding new apps/packages

**Cons**:
- Steeper learning curve than Turborepo
- Opinionated folder structure
- Heavier tooling (larger node_modules)

## Decision Outcome

**Chosen option**: Option B - Monorepo with Turborepo

**Justification**: The agentic-app-pipeline-starter template already uses Turborepo, and we have mobile (`apps/mobile`) and web (`apps/web`) scaffolded. Extending this to include Laravel backend as `apps/api` leverages existing tooling while maintaining deployment independence. Turborepo's caching and parallel execution provide fast builds without Nx's complexity.

## Implementation Details

### Directory Structure

```
/Users/claude-micaelguinan/aria/
├── apps/
│   ├── api/                      # Laravel 11 backend
│   │   ├── app/
│   │   │   ├── Http/Controllers/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   ├── Contracts/         # Interfaces (e.g., PaymentProvider)
│   │   │   └── Jobs/
│   │   ├── database/
│   │   │   ├── migrations/
│   │   │   └── seeders/
│   │   ├── routes/
│   │   │   ├── api.php
│   │   │   └── web.php
│   │   ├── config/
│   │   ├── tests/
│   │   ├── artisan
│   │   ├── composer.json
│   │   └── phpunit.xml
│   │
│   ├── web/                      # Next.js public website (Cloudflare Pages)
│   │   ├── app/                   # App Router (Next.js 14+)
│   │   │   ├── events/
│   │   │   ├── checkout/
│   │   │   └── layout.tsx
│   │   ├── components/
│   │   ├── lib/
│   │   ├── public/
│   │   ├── next.config.js        # Cloudflare Pages adapter
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── dashboard/                # Inertia.js organizer dashboard (NEW)
│   │   ├── resources/
│   │   │   ├── js/
│   │   │   │   ├── Pages/         # Inertia pages (Events, Orders, Analytics)
│   │   │   │   ├── Components/
│   │   │   │   └── app.tsx
│   │   │   └── css/
│   │   ├── routes/web.php        # Shares Laravel backend
│   │   ├── vite.config.js
│   │   └── package.json
│   │
│   └── mobile/                   # Expo React Native (existing)
│       ├── app/                   # Expo Router
│       ├── components/
│       ├── lib/
│       ├── app.json
│       ├── eas.json
│       └── package.json
│
├── packages/
│   ├── types/                     # Shared TypeScript types
│   │   ├── src/
│   │   │   ├── api/               # API request/response types (generated from Laravel)
│   │   │   ├── entities/          # Domain entities (Event, Order, Ticket)
│   │   │   └── enums/             # Shared enums (PaymentStatus, OrderState)
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── ui/                        # Shared React components (existing)
│   │   ├── src/
│   │   │   ├── Button.tsx
│   │   │   ├── Input.tsx
│   │   │   └── EventCard.tsx
│   │   └── package.json
│   │
│   ├── utils/                     # Shared utilities
│   │   ├── src/
│   │   │   ├── currency.ts        # XOF formatting
│   │   │   ├── dates.ts           # Timezone handling
│   │   │   └── validation.ts      # Phone number (E.164), email
│   │   └── package.json
│   │
│   └── api-client/                # Generated API client (TypeScript)
│       ├── src/
│       │   ├── events.ts          # EventsAPI methods
│       │   ├── orders.ts
│       │   └── tickets.ts
│       └── package.json
│
├── infra/
│   ├── docker/
│   │   ├── api.Dockerfile
│   │   └── docker-compose.yml
│   ├── k8s/                       # Kubernetes manifests (optional)
│   ├── cloudflare/                # Cloudflare Pages config
│   └── terraform/                 # IaC for managed services
│
├── docs/
│   ├── adr/
│   ├── product_specs/
│   └── api/                       # OpenAPI schema (generated)
│
├── scripts/
│   ├── generate-types.sh          # Laravel → TypeScript type generation
│   └── db-seed.sh
│
├── turbo.json                     # Turborepo pipeline config
├── package.json                   # Root package (workspaces)
├── pnpm-workspace.yaml            # Package manager workspaces
└── README.md
```

### Laravel Backend Architecture (apps/api)

**Layered Architecture**:

```
┌─────────────────────────────────────────┐
│   HTTP Layer (Routes, Controllers)      │
│   - api.php: /v1/events, /v1/orders     │
│   - Validation: FormRequest classes     │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│   Service Layer (Business Logic)        │
│   - EventService, OrderService          │
│   - PaymentService, PayoutService       │
│   - State machine transitions           │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│   Repository Layer (Data Access)        │
│   - EventRepository (query scopes)      │
│   - Eloquent ORM abstractions           │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│   Models (Eloquent ORM)                  │
│   - Event, Order, Payment, Ticket       │
│   - Relationships, Accessors/Mutators   │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│   Database (PostgreSQL 16)               │
└─────────────────────────────────────────┘
```

**Example Service**:

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Event;
use App\Exceptions\InsufficientInventoryException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(Event $event, array $items, array $buyer): Order
    {
        return DB::transaction(function () use ($event, $items, $buyer) {
            // Validate inventory
            foreach ($items as $item) {
                $ticketType = $event->ticketTypes()->findOrFail($item['ticket_type_id']);
                $available = $ticketType->max_qty - $ticketType->sold_count;

                if ($item['qty'] > $available) {
                    throw new InsufficientInventoryException($ticketType->name);
                }
            }

            // Create order with expiry
            $order = Order::create([
                'event_id' => $event->id,
                'buyer_user_id' => $buyer['user_id'] ?? null,
                'email' => $buyer['email'],
                'phone' => $buyer['phone'],
                'state' => OrderState::CREATED,
                'amount_total' => $this->calculateTotal($items),
                'idempotency_key' => Str::uuid(),
                'expires_at' => now()->addMinutes(10),
            ]);

            // Create order items and reserve inventory
            foreach ($items as $item) {
                $ticketType = $event->ticketTypes()->find($item['ticket_type_id']);
                $ticketType->increment('reserved_count', $item['qty']);

                $order->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'qty' => $item['qty'],
                    'unit_price_xof' => $ticketType->price_xof,
                    'line_total' => $ticketType->price_xof * $item['qty'],
                ]);
            }

            // Transition to awaiting_payment
            $order->transitionTo(OrderState::AWAITING_PAYMENT);

            return $order;
        });
    }
}
```

### Inertia.js Dashboard (apps/dashboard)

Inertia.js runs **within** the Laravel backend (`apps/api`) but we separate frontend assets for clarity.

**Integration approach**:
- `apps/api/resources/js/Pages/` contains Inertia pages
- Laravel routes in `routes/web.php` return Inertia responses
- Vite bundles TypeScript/React assets
- Deployed together with API (same Docker image)

**Example route**:

```php
<?php
// apps/api/routes/web.php

use Inertia\Inertia;

Route::middleware(['auth', 'org.member'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard/Index', [
            'events' => auth()->user()->currentOrg->events()->get(),
        ]);
    });

    Route::get('/events/{event}/sales', function (Event $event) {
        return Inertia::render('Events/Sales', [
            'event' => $event,
            'sales' => $event->orders()->with('items')->paginate(50),
        ]);
    });
});
```

**Inertia Page Component**:

```tsx
// apps/api/resources/js/Pages/Events/Sales.tsx
import { Head } from '@inertiajs/react';
import { EventSalesTable } from '@/Components/EventSalesTable';
import { Event, Order } from '@aria/types';

export default function EventSales({ event, sales }: { event: Event; sales: Order[] }) {
  return (
    <>
      <Head title={`${event.title} - Sales`} />
      <div className="p-6">
        <h1 className="text-2xl font-bold">{event.title}</h1>
        <EventSalesTable orders={sales} />
      </div>
    </>
  );
}
```

### Next.js Public Website (apps/web)

**Cloudflare Pages Configuration**:

```js
// apps/web/next.config.js
/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export', // Static export for Cloudflare Pages
  images: {
    unoptimized: true, // Cloudflare Images handles optimization
  },
  trailingSlash: true,

  // Edge-safe API calls (no Node.js APIs)
  experimental: {
    runtime: 'edge',
  },
};

module.exports = nextConfig;
```

**API Client Usage**:

```tsx
// apps/web/app/events/[slug]/page.tsx
import { EventsAPI } from '@aria/api-client';
import { EventDetailCard } from '@aria/ui';

export default async function EventPage({ params }: { params: { slug: string } }) {
  const event = await EventsAPI.getBySlug(params.slug);

  return (
    <div>
      <EventDetailCard event={event} />
      {/* Checkout button → client component with server action */}
    </div>
  );
}
```

### Shared Packages

**packages/types (Generated from Laravel Models)**:

```bash
# scripts/generate-types.sh
#!/bin/bash
cd apps/api
php artisan model:typescript --output=../../packages/types/src/api/
```

**Generated Output**:

```ts
// packages/types/src/api/Event.ts
export interface Event {
  id: string;
  org_id: string;
  title: string;
  slug: string;
  description_md: string;
  start_at: string; // ISO 8601
  end_at: string;
  venue_name: string;
  venue_address: string;
  status: 'draft' | 'published' | 'canceled' | 'ended';
  created_at: string;
  updated_at: string;
  ticket_types?: TicketType[];
}
```

**packages/api-client (Fetch Wrapper)**:

```ts
// packages/api-client/src/events.ts
import { Event } from '@aria/types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

export const EventsAPI = {
  async getBySlug(slug: string): Promise<Event> {
    const res = await fetch(`${API_BASE}/events/${slug}`);
    const { data } = await res.json();
    return data;
  },

  async search(params: { q?: string; city?: string; from?: string; to?: string }): Promise<Event[]> {
    const query = new URLSearchParams(params as any).toString();
    const res = await fetch(`${API_BASE}/events?${query}`);
    const { data } = await res.json();
    return data;
  },
};
```

### Build Orchestration (Turborepo)

```json
// turbo.json
{
  "$schema": "https://turbo.build/schema.json",
  "pipeline": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": ["dist/**", "build/**", ".next/**", "public/build/**"]
    },
    "test": {
      "dependsOn": ["build"],
      "outputs": []
    },
    "lint": {
      "outputs": []
    },
    "dev": {
      "cache": false,
      "persistent": true
    }
  }
}
```

**Parallel Build Execution**:

```bash
# Build all apps and packages (parallelized)
turbo run build

# Build only affected packages after changes
turbo run build --filter=...@aria/types

# Run dev servers in parallel
turbo run dev --parallel
```

### CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  build-and-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: oven-sh/setup-bun@v1

      - name: Install dependencies
        run: bun install

      - name: Build all packages
        run: turbo run build

      - name: Run tests
        run: turbo run test

  deploy-api:
    needs: build-and-test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy Laravel API
        run: |
          cd apps/api
          php artisan migrate --force
          # Deploy to container registry

  deploy-web:
    needs: build-and-test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Cloudflare Pages
        run: |
          cd apps/web
          bunx wrangler pages deploy ./out --project-name=aria-web

  deploy-mobile:
    needs: build-and-test
    if: contains(github.event.head_commit.message, '[mobile]')
    runs-on: ubuntu-latest
    steps:
      - name: Build and submit to EAS
        run: |
          cd apps/mobile
          eas build --platform all --non-interactive
```

### Dependency Management

**Root package.json**:

```json
{
  "name": "aria-monorepo",
  "private": true,
  "workspaces": [
    "apps/*",
    "packages/*"
  ],
  "scripts": {
    "dev": "turbo run dev --parallel",
    "build": "turbo run build",
    "test": "turbo run test",
    "lint": "turbo run lint",
    "generate:types": "./scripts/generate-types.sh"
  },
  "devDependencies": {
    "turbo": "^1.11.0",
    "typescript": "^5.3.0"
  }
}
```

**Package Dependencies**:

```json
// apps/web/package.json
{
  "name": "@aria/web",
  "dependencies": {
    "next": "^14.0.0",
    "react": "^18.2.0",
    "@aria/types": "*",
    "@aria/ui": "*",
    "@aria/api-client": "*"
  }
}
```

## Consequences

### Positive
- **Type safety**: API contracts shared via generated types; impossible to have version skew
- **Code reuse**: UI components, utilities, and types shared across web and mobile
- **Atomic refactors**: Change API response shape and update all consumers in one PR
- **Fast builds**: Turborepo caching means only changed packages rebuild
- **Deployment independence**: API, web, and mobile deploy separately despite shared codebase

### Negative
- **Repository size**: Larger clone times (mitigated by shallow clones and sparse checkout)
- **Build complexity**: Developers must understand workspace dependencies and turbo pipeline
- **Merge conflicts**: More developers working in single repo increases conflict potential

### Risks and Mitigations

**Risk**: Turborepo cache invalidation issues causing stale builds
**Mitigation**: Use `--force` flag in CI; document cache clearing steps; version turbo.json

**Risk**: Laravel and Next.js running on different Node.js/PHP versions locally
**Mitigation**: Use `.tool-versions` (asdf) or Docker Compose for consistent environments

**Risk**: Monorepo becomes too large (>10GB) over time
**Mitigation**: Git LFS for large assets; exclude build artifacts from version control; periodic git gc

## References
- DESIGN.md Section 4: High-Level Architecture
- DESIGN.md Section 16: Tech Stack Choices
- DESIGN.md Section 27: Mobile App + Agentic Pipeline Alignment
- Product Spec: aria-mvp.md (Technology Stack section)
- External: [Turborepo Docs](https://turbo.build/repo/docs), [Inertia.js](https://inertiajs.com/), [Next.js on Cloudflare](https://developers.cloudflare.com/pages/framework-guides/nextjs/)
