# Agentic App Pipeline Starter

End-to-end loop: Idea → Spec → Tasks → PRs → Preview → QA → Release (TestFlight/Play) using Cursor, Claude Code subagents, Copilot Workspace, and GitHub Actions with EAS/fastlane.

## Quick Start
1. **Create repo** and push this tree.
2. **Secrets** (GitHub → Settings → Secrets and variables → Actions):
   - `EXPO_TOKEN` (Expo access token)
   - `APPLE_API_KEY_ID`, `APPLE_ISSUER_ID`, `APPLE_API_KEY_CONTENT` (or use fastlane match)
   - `PLAY_SERVICE_ACCOUNT_JSON` (base64 or JSON for Play API)
3. **Enable Copilot Workspace** for your org/repo.
4. **Install Cursor** locally; it will read `.cursorrules`.
5. **Create first idea issue** via template. Label it `workspace-ready` to prime Workspace.

### Flow
- Planner subagent (Claude Code) expands the idea into a spec + issues.
- You open a task in Copilot Workspace → produces a PR.
- Cursor helps apply repo-wide edits/refactors.
- CI runs tests, static analysis, and EAS preview builds (links posted in PR).
- Tag `v0.1.0` or comment `/release v0.1.0` → release job submits to TestFlight/Play Internal.

## Monorepo Structure

This project uses a monorepo structure with Turborepo for efficient build orchestration:

```
aria/
├── apps/
│   ├── web/        # Next.js web app (@aria/web)
│   ├── mobile/     # Expo mobile app (@aria/mobile)
│   └── api/        # Laravel API (@aria/api)
└── packages/
    ├── types/      # Shared TypeScript types (@aria/types)
    ├── utils/      # Shared utilities (@aria/utils)
    ├── api-client/ # Type-safe API client (@aria/api-client)
    └── ui/         # Shared UI components (@aria/ui)
```

## Development

### Prerequisites
- Bun 1.0+
- PHP 8.3+ (for Laravel API)
- PostgreSQL 16+

### Quick Start

```bash
# Install dependencies
bun install

# Start all development servers
bun run dev

# Build all apps and packages
bun run build

# Run tests
bun run test

# Lint code
bun run lint

# Type check
bun run typecheck
```

### Working with Specific Apps

```bash
# Start only web app
bun run dev:web

# Start only mobile app
bun run dev:mobile

# Start only API
bun run dev:api

# Build only web app
turbo run build --filter=@aria/web

# Test API only
turbo run test --filter=@aria/api
```

### Turborepo Commands

```bash
# Clear cache and rebuild everything
turbo run build --force

# Run task with verbose logging
turbo run build --verbose

# Dry run to see what would execute
turbo run build --dry-run
```

### Port Configuration
- API (Laravel): 8000
- Web (Next.js): 3000
- Mobile (Expo): 8081
- Vite HMR (Inertia): 5173

## Shared Packages

The monorepo includes several shared packages that provide type-safe API communication and utilities:

### @aria/types - TypeScript Type Definitions

Contains TypeScript interfaces for all Laravel models and shared enums.

```typescript
import { Event, EventStatus, User, Order } from '@aria/types';

const event: Event = {
  id: 'uuid',
  title: 'My Event',
  status: EventStatus.Published,
  // ... other fields
};
```

**Type Generation:**
Types are currently manually maintained in `packages/types/src/api/`. After Laravel schema changes:

```bash
# Run type generation script (placeholder for future automation)
bun run generate:types

# Verify types match Laravel models
bun run typecheck
```

### @aria/utils - Shared Utilities

Utilities for currency formatting, date handling, and validation.

```typescript
import { formatXOF, parseXOF, isValidE164Phone, formatEventDate } from '@aria/utils';

// Currency formatting (XOF with space separator)
formatXOF(5000); // "5 000 XOF"
parseXOF("1 500 000 XOF"); // 1500000

// Phone validation (E.164 format)
isValidE164Phone("+2250707123456"); // true

// Date formatting (Africa/Abidjan timezone)
formatEventDate("2025-11-20T14:30:00Z"); // "Nov 20, 2025, 2:30 PM"
```

### @aria/api-client - Type-Safe API Client

Provides a type-safe wrapper around the Laravel API with full TypeScript autocomplete.

```typescript
import { createAPIClient } from '@aria/api-client';

// Create client instance
const api = createAPIClient({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  getAuthToken: () => localStorage.getItem('token'),
});

// Use the API client with full type safety
const events = await api.events.search({ category: 'music' });
const event = await api.events.getBySlug('my-event-slug');

// Create order
const order = await api.orders.create('event-slug', {
  buyer_email: 'user@example.com',
  buyer_phone: '+2250707123456',
  buyer_name: 'John Doe',
  items: [{ ticket_type_id: 'uuid', qty: 2 }],
});

// Check in ticket (requires auth)
const ticket = await api.tickets.checkIn('ticket-code');
```

**Environment Variables:**

For Next.js (web app):
```bash
NEXT_PUBLIC_API_URL=https://api.aria.ci
```

For Expo (mobile app):
```bash
EXPO_PUBLIC_API_URL=https://api.aria.ci
```

## Web (Cloudflare Pages)
- Connect repo in Cloudflare Pages and set secrets in GitHub: CLOUDFLARE_API_TOKEN, CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_PROJECT_NAME.
- Deploys on push to main affecting apps/web or packages/.
