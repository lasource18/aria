# ADR-0008: REST API Design Standards

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, backend, api, conventions]

## Context and Problem Statement

Aria's REST API serves web, mobile, and future third-party integrations. Without consistent design standards, we risk API inconsistencies that create frontend bugs and poor developer experience. We need clear conventions for:
- Resource naming (plural vs singular, kebab-case vs snake_case)
- Request/response formats (envelope structure, error handling)
- Pagination, filtering, sorting
- Versioning strategy
- Idempotency and retries

**Referenced sections**: DESIGN.md Section 9 (APIs)

## Decision Outcome

**Chosen Standards**:

### 1. Resource Naming
- **Plural nouns**: `/events`, `/orders`, `/tickets`
- **Kebab-case for URLs**: `/ticket-types` (not `/ticketTypes`)
- **snake_case for JSON**: `{"ticket_type_id": "..."}`
- **Version prefix**: `/api/v1/events`

### 2. Response Envelope
```json
{
  "data": { ... },
  "meta": { "pagination": { ... } },
  "error": null
}
```

**Error Response**:
```json
{
  "data": null,
  "error": {
    "code": "INSUFFICIENT_INVENTORY",
    "message": "Only 3 tickets remaining",
    "details": { "available": 3, "requested": 5 }
  }
}
```

### 3. Pagination
- **Cursor-based** for infinite scroll (orders, tickets): `?cursor=eyJpZCI6...&limit=50`
- **Offset-based** for page navigation (events): `?page=2&per_page=20`

```json
{
  "data": [...],
  "meta": {
    "pagination": {
      "next_cursor": "eyJpZCI6...",
      "has_more": true,
      "total": 1234
    }
  }
}
```

### 4. Filtering and Sorting
- **Filters**: `?category=music&city=abidjan&price_min=0&price_max=10000`
- **Sorting**: `?sort=-start_at,title` (minus for descending)
- **Search**: `?q=festival` (full-text search)

### 5. Idempotency
- **Header**: `X-Idempotency-Key: <uuid>`
- **Scope**: POST/PUT/PATCH/DELETE requests
- **Storage**: 24-hour key retention in Redis

### 6. Rate Limiting
- **Per user**: 1000 requests/hour
- **Per IP**: 100 requests/minute (unauthenticated)
- **Headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

### 7. Versioning
- **URL prefix**: `/api/v1/`, `/api/v2/`
- **Deprecation policy**: v1 supported for 12 months after v2 release
- **Sunset header**: `Sunset: Sat, 01 Jan 2026 00:00:00 GMT`

## Implementation Examples

```php
<?php
// app/Http/Controllers/Api/BaseController.php
namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

abstract class BaseController extends Controller
{
    protected function successResponse($data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'error' => null,
        ], $status);
    }

    protected function errorResponse(string $code, string $message, array $details = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
```

## References
- DESIGN.md Section 9: APIs (Errors & Conventions)
- External: [JSON:API](https://jsonapi.org/), [REST Best Practices](https://restfulapi.net/)
