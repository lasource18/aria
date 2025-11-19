# ADR-0006: Multi-Tenant RBAC Model

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, backend, security, authorization, rbac]

## Context and Problem Statement

Aria supports two distinct permission scopes:

1. **Organization-scoped permissions**: Organizers manage their own events, view their sales data, configure payouts, and invite team members with different roles (owner, admin, staff, finance)
2. **Platform-scoped permissions**: Platform administrators review high-risk events, approve payouts, configure fees, and moderate content

Without a clear RBAC (Role-Based Access Control) model, we risk:
- **Privilege escalation**: Staff users accessing financial data or payout settings
- **Data leakage**: Users viewing other organizations' events or attendee lists
- **Audit failures**: Unable to prove who approved a payout or refund
- **Inconsistent enforcement**: Authorization checks scattered across controllers vs centralized policies

**Referenced sections**: DESIGN.md Section 12 (Security & Risk - RBAC), Section 1 (Personas)

## Decision Drivers

- **Multi-tenancy**: Users can belong to multiple organizations with different roles
- **Least privilege**: Default deny; explicit grants for each action (view event, edit tickets, approve payout)
- **Separation of concerns**: Platform admins vs organization members have distinct permission sets
- **Performance**: Authorization checks must not add >10ms latency to API requests
- **Auditability**: All permission-gated actions logged for compliance
- **Extensibility**: Support future roles (e.g., "marketing" role with promo code access only)

## Considered Options

### Option A: Simple Role Column on Users Table
Add `role` column to `users` table with enum (admin, organizer, staff).

**Pros**:
- Simplest implementation
- Fast authorization checks (single column lookup)

**Cons**:
- Doesn't support multi-tenancy (user can only have one role globally)
- No org-scoped permissions (can't differentiate "admin of Org A" vs "staff of Org B")
- Requires complete schema rewrite to add multi-tenancy later

### Option B: Organization Membership with Roles (Recommended)
Create `org_members` join table with `role` column; users have different roles per organization.

**Pros**:
- Native multi-tenancy (user can be owner of Org A, staff of Org B)
- Org-scoped permissions enforced at database level (foreign key constraints)
- Extensible to add fine-grained permissions per role

**Cons**:
- Requires join on every org-scoped query
- Slightly more complex than global role column

### Option C: Permission Tables with Many-to-Many Relationships
Create `permissions` table with granular permissions (e.g., `events.create`, `payouts.approve`); users assigned permissions via pivot table.

**Pros**:
- Maximum flexibility (permissions assigned individually, not just by role)
- Supports complex permission hierarchies

**Cons**:
- Over-engineered for MVP (no requirement for per-user permission overrides)
- Slower authorization checks (requires multiple joins)
- Harder to reason about "what can this user do?"

## Decision Outcome

**Chosen option**: Option B - Organization Membership with Roles

**Justification**: Aria's MVP requires org-scoped roles (owner, admin, staff, finance) plus platform-scoped roles (platform admin). Option B naturally models this with `org_members` for org roles and a `is_platform_admin` boolean on `users` for platform roles. Option C (granular permissions) is deferred to post-MVP if enterprise customers need custom permission overrides.

## Implementation Details

### Database Schema

```sql
-- Users (platform-scoped)
CREATE TABLE users (
    id UUID PRIMARY KEY,
    email TEXT UNIQUE NOT NULL,
    full_name TEXT NOT NULL,
    is_platform_admin BOOLEAN DEFAULT FALSE,  -- Platform admin flag
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Organizations
CREATE TABLE orgs (
    id UUID PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Organization members (multi-tenant RBAC)
CREATE TABLE org_members (
    id UUID PRIMARY KEY,
    org_id UUID NOT NULL REFERENCES orgs(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role TEXT NOT NULL CHECK (role IN ('owner', 'admin', 'staff', 'finance')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(org_id, user_id)  -- User can only have one role per org
);

CREATE INDEX idx_org_members_user_id ON org_members(user_id);
CREATE INDEX idx_org_members_org_id ON org_members(org_id);
```

### Role Definitions

| Role | Scope | Permissions |
|------|-------|-------------|
| **Platform Admin** | Platform | View all orgs/events, approve payouts, configure fees, moderate content, view audit logs |
| **Owner** | Organization | Full control: create/edit/delete events, manage team members, configure payouts, view financials |
| **Admin** | Organization | Create/edit events, view sales/attendees, initiate payouts (subject to approval), manage ticket types |
| **Staff** | Organization | Check in tickets, view attendee lists (no financial data), edit event details (no publish/cancel) |
| **Finance** | Organization | View financials, download reports, initiate payouts (no event editing) |

### Laravel Policies (Authorization)

**Event Policy**:

```php
<?php

namespace App\Policies;

use App\Models\{User, Event, Org};

class EventPolicy
{
    /**
     * Determine if user can view event (public events visible to all).
     */
    public function view(?User $user, Event $event): bool
    {
        if ($event->status === 'published') {
            return true;  // Public event
        }

        // Draft/canceled events: only org members can view
        return $user && $event->org->hasMember($user);
    }

    /**
     * Determine if user can update event.
     */
    public function update(User $user, Event $event): bool
    {
        return $event->org->hasMemberWithRole($user, ['owner', 'admin']);
    }

    /**
     * Determine if user can delete event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->org->hasMemberWithRole($user, ['owner']);
    }

    /**
     * Determine if user can publish event.
     */
    public function publish(User $user, Event $event): bool
    {
        return $event->org->hasMemberWithRole($user, ['owner', 'admin']);
    }

    /**
     * Determine if user can view attendee list.
     */
    public function viewAttendees(User $user, Event $event): bool
    {
        return $event->org->hasMember($user);  // All org members
    }
}
```

**Org Policy**:

```php
<?php

namespace App\Policies;

use App\Models\{User, Org};

class OrgPolicy
{
    public function view(User $user, Org $org): bool
    {
        return $org->hasMember($user) || $user->is_platform_admin;
    }

    public function update(User $user, Org $org): bool
    {
        return $org->hasMemberWithRole($user, ['owner', 'admin']);
    }

    public function manageMembers(User $user, Org $org): bool
    {
        return $org->hasMemberWithRole($user, ['owner']);
    }

    public function viewFinancials(User $user, Org $org): bool
    {
        return $org->hasMemberWithRole($user, ['owner', 'admin', 'finance']);
    }

    public function initiatePayout(User $user, Org $org): bool
    {
        return $org->hasMemberWithRole($user, ['owner', 'finance']);
    }
}
```

**Payout Policy**:

```php
<?php

namespace App\Policies;

use App\Models\{User, Payout};

class PayoutPolicy
{
    public function approve(User $user, Payout $payout): bool
    {
        // Only platform admins can approve payouts
        return $user->is_platform_admin;
    }

    public function view(User $user, Payout $payout): bool
    {
        return $payout->org->hasMemberWithRole($user, ['owner', 'finance'])
            || $user->is_platform_admin;
    }
}
```

### Org Model (Helper Methods)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Org extends Model
{
    public function members()
    {
        return $this->hasMany(OrgMember::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function hasMemberWithRole(User $user, array $roles): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();
    }

    public function getMemberRole(User $user): ?string
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->value('role');
    }
}
```

### Middleware (Org Context)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Org;

class SetOrgContext
{
    /**
     * Inject current org into request based on route parameter.
     */
    public function handle(Request $request, Closure $next)
    {
        $orgId = $request->route('orgId') ?? $request->route('org')?->id;

        if ($orgId) {
            $org = Org::findOrFail($orgId);

            // Verify user is member of this org
            if (!$org->hasMember($request->user())) {
                abort(403, 'You are not a member of this organization');
            }

            // Attach org to request for easy access
            $request->merge(['currentOrg' => $org]);
        }

        return $next($request);
    }
}
```

### Controller Authorization

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\{Event, Org};
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'org.context']);
    }

    public function update(Request $request, Event $event)
    {
        // Policy authorization
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description_md' => 'sometimes|string',
        ]);

        $event->update($validated);

        // Audit log
        \App\Models\AuditLog::create([
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'action' => 'updated',
            'actor_user_id' => $request->user()->id,
            'new_state' => $validated,
        ]);

        return response()->json(['data' => $event], 200);
    }

    public function publish(Request $request, Event $event)
    {
        $this->authorize('publish', $event);

        $event->transitionTo(new \App\States\Event\PublishedState($event));

        return response()->json(['data' => $event], 200);
    }
}
```

### Route Definitions with Middleware

```php
<?php

// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{EventController, PayoutController};

// Public routes (no auth)
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);

// Organization-scoped routes (requires auth + org membership)
Route::middleware(['auth:sanctum', 'org.context'])->group(function () {
    Route::prefix('orgs/{orgId}')->group(function () {
        // Events
        Route::post('/events', [EventController::class, 'store']);
        Route::patch('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);
        Route::post('/events/{event}/publish', [EventController::class, 'publish']);

        // Payouts (finance role required)
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts', [PayoutController::class, 'store'])
            ->middleware('can:initiatePayout,orgId');
    });
});

// Platform admin routes
Route::middleware(['auth:sanctum', 'platform.admin'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/orgs', [AdminController::class, 'listOrgs']);
        Route::post('/payouts/{payout}/approve', [AdminController::class, 'approvePayout']);
        Route::post('/orgs/{org}/verify', [AdminController::class, 'verifyOrg']);
    });
});
```

### Platform Admin Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->is_platform_admin) {
            abort(403, 'Platform admin access required');
        }

        return $next($request);
    }
}
```

### User Model (Current Org Helper)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /**
     * Get user's current organization (from session or header).
     */
    public function currentOrg(): ?Org
    {
        $orgId = session('current_org_id') ?? request()->header('X-Current-Org');

        if (!$orgId) {
            return $this->orgs()->first();  // Default to first org
        }

        return $this->orgs()->find($orgId);
    }

    public function orgs()
    {
        return $this->belongsToMany(Org::class, 'org_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }

    public function hasRoleInOrg(Org $org, array $roles): bool
    {
        return $org->hasMemberWithRole($this, $roles);
    }
}
```

### Testing Authorization

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Org, Event};

class EventAuthorizationTest extends TestCase
{
    /** @test */
    public function owner_can_delete_event()
    {
        $owner = User::factory()->create();
        $org = Org::factory()->create();
        $org->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $event = Event::factory()->create(['org_id' => $org->id]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/orgs/{$org->id}/events/{$event->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    /** @test */
    public function staff_cannot_delete_event()
    {
        $staff = User::factory()->create();
        $org = Org::factory()->create();
        $org->members()->create(['user_id' => $staff->id, 'role' => 'staff']);

        $event = Event::factory()->create(['org_id' => $org->id]);

        $this->actingAs($staff)
            ->deleteJson("/api/v1/orgs/{$org->id}/events/{$event->id}")
            ->assertStatus(403);
    }

    /** @test */
    public function user_from_different_org_cannot_view_draft_event()
    {
        $user = User::factory()->create();
        $otherOrg = Org::factory()->create();
        $otherOrg->members()->create(['user_id' => $user->id, 'role' => 'owner']);

        $event = Event::factory()->create(['status' => 'draft']);

        $this->actingAs($user)
            ->getJson("/api/v1/events/{$event->slug}")
            ->assertStatus(403);
    }
}
```

## Consequences

### Positive
- **Multi-tenancy**: Users can belong to multiple organizations with different roles
- **Least privilege**: Default deny enforced by Laravel policies; explicit grants per action
- **Performance**: Single join on `org_members` adds <5ms to queries; acceptable overhead
- **Auditability**: All policy-gated actions logged with actor, timestamp, and reason
- **Extensibility**: New roles (e.g., "marketing") added without schema changes

### Negative
- **Complexity**: More complex than global role column (but necessary for multi-tenancy)
- **Query overhead**: Requires join on every org-scoped query (mitigated by indexes)
- **Testing burden**: Must test authorization for every role/action combination

### Risks and Mitigations

**Risk**: Developers forget to add authorization checks to new endpoints
**Mitigation**: Code review checklist includes "Does this endpoint call `$this->authorize()`?"; automated policy coverage tests

**Risk**: Org membership join adds latency to hot queries
**Mitigation**: Cache user's org memberships in Redis with 5-minute TTL; eager load `org_members` on user login

**Risk**: Role definitions become inconsistent (staff has more permissions than admin)
**Mitigation**: Document role permission matrix in this ADR; centralize role definitions in config file

## References
- DESIGN.md Section 12: Security & Risk (RBAC)
- DESIGN.md Section 1: Product Overview (Personas)
- Product Spec: aria-mvp.md User Story 4 (Platform admin reviews payouts)
- External: [Laravel Authorization](https://laravel.com/docs/11.x/authorization), [OWASP RBAC](https://owasp.org/www-community/Access_Control)
