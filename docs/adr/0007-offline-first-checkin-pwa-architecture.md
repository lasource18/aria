# ADR-0007: Offline-First Check-in PWA Architecture

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, frontend, pwa, offline, mobile]

## Context and Problem Statement

Aria's check-in staff operate at venues with unreliable internet connectivity (slow 3G, intermittent WiFi, crowded networks). The check-in experience must:
- **Work offline**: Staff can scan tickets and mark check-ins when completely offline
- **Sync on reconnect**: Queue local check-ins and sync to server when connection restored
- **Prevent duplicates**: Handle conflict resolution if same ticket scanned on multiple devices offline
- **Verify authenticity**: Validate ticket QR codes offline using cryptographic signatures
- **Fast UX**: <500ms from QR scan to "VALID" or "ALREADY CHECKED IN" display

**Referenced sections**: DESIGN.md Section 11 (Check-in PWA), Product Spec User Story 3

## Decision Drivers

- **Offline capability**: Must function without internet for hours (entire event duration)
- **Security**: Tickets must be cryptographically verifiable offline (prevent fake QR codes)
- **Conflict resolution**: Server wins on sync if same ticket scanned on multiple offline devices
- **Storage limits**: Mobile browsers allow ~50MB IndexedDB; must fit ticket data for 10K-attendee events
- **Cross-platform**: Must work on iOS Safari, Android Chrome, and desktop browsers

## Considered Options

### Option A: Progressive Web App (PWA) with Service Worker + IndexedDB
Build PWA using Inertia.js page with Service Worker for offline caching; store ticket snapshots in IndexedDB.

**Pros**: Cross-platform (works on all modern browsers); no app store approval needed; instant updates
**Cons**: iOS Safari has limited Service Worker support; IndexedDB quotas strict on iOS

### Option B: Native Mobile App (React Native)
Build dedicated check-in app with offline SQLite database.

**Pros**: Full offline capabilities; larger storage limits; better camera access
**Cons**: Requires app store approval; separate codebase from web; longer release cycle

### Option C: Hybrid (PWA + Optional Native Wrapper)
Start with PWA for MVP; wrap in Capacitor for native app if needed post-MVP.

**Pros**: Fast MVP timeline; upgrade path to native if offline constraints too restrictive
**Cons**: Two deployment targets to maintain

## Decision Outcome

**Chosen option**: Option A - PWA with Service Worker + IndexedDB (Recommended for MVP)

**Justification**: PWA meets offline requirements without app store gatekeepers. IndexedDB quota (50MB) sufficient for 10K tickets (5KB/ticket = 50MB). Inertia.js page shares Laravel auth session. If iOS limitations discovered post-MVP, wrap in Capacitor (Option C).

## Implementation Details

### Architecture Overview

```
┌─────────────────────────────────────────┐
│   Check-in PWA (Inertia.js Page)        │
│                                          │
│  ┌────────────────────────────────────┐ │
│  │  UI Layer (React Components)       │ │
│  │  - QR Scanner                       │ │
│  │  - Ticket List                      │ │
│  │  - Sync Status Indicator            │ │
│  └────────────────────────────────────┘ │
│                ↓                         │
│  ┌────────────────────────────────────┐ │
│  │  State Management (Zustand)        │ │
│  │  - Tickets (local cache)            │ │
│  │  - Pending check-ins queue          │ │
│  │  - Online/offline status            │ │
│  └────────────────────────────────────┘ │
│                ↓                         │
│  ┌────────────────────────────────────┐ │
│  │  Service Worker (Offline Logic)    │ │
│  │  - Cache API responses              │ │
│  │  - Intercept network requests       │ │
│  │  - Background sync when online      │ │
│  └────────────────────────────────────┘ │
│                ↓                         │
│  ┌────────────────────────────────────┐ │
│  │  IndexedDB (Persistent Storage)    │ │
│  │  - tickets: {code, state, qr_sig}  │ │
│  │  - checkins: {ticket_id, ts, device}│ │
│  └────────────────────────────────────┘ │
└─────────────────────────────────────────┘
                 ↕
    ┌───────────────────────────┐
    │   Laravel API             │
    │   /api/v1/events/{id}/    │
    │     - GET /tickets        │
    │     - POST /checkins      │
    └───────────────────────────┘
```

### Ticket QR Code Format (Offline Verification)

```
QR Code Data: ticket:A3B7K2M9|sig:hmacSHA256Base64
```

**Signature Generation (Server-side)**:
```php
<?php
$ticketCode = 'A3B7K2M9';
$secret = config('app.ticket_signing_secret');  // Rotated secret
$signature = base64_encode(hash_hmac('sha256', $ticketCode, $secret, true));
$qrData = "ticket:{$ticketCode}|sig:{$signature}";
```

**Signature Verification (Client-side, Offline)**:
```typescript
// packages/utils/src/ticketVerification.ts
export function verifyTicketSignature(qrData: string): { valid: boolean; code: string } {
  const [ticketPart, sigPart] = qrData.split('|');
  const ticketCode = ticketPart.replace('ticket:', '');
  const receivedSig = sigPart.replace('sig:', '');

  // Fetch signing secret from encrypted IndexedDB (downloaded on staff login)
  const secret = await getSigningSecret();
  const expectedSig = await crypto.subtle.sign(
    { name: 'HMAC', hash: 'SHA-256' },
    await crypto.subtle.importKey('raw', new TextEncoder().encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']),
    new TextEncoder().encode(ticketCode)
  );

  const valid = expectedSig === atob(receivedSig);
  return { valid, code: ticketCode };
}
```

### IndexedDB Schema

```typescript
// apps/api/resources/js/lib/db.ts
import Dexie, { Table } from 'dexie';

export interface Ticket {
  id: string;
  code: string;
  event_id: string;
  ticket_type_name: string;
  owner_name: string;
  state: 'issued' | 'checked_in' | 'void';
  qr_signature: string;
  synced_at: Date;
}

export interface PendingCheckin {
  id: string;
  ticket_code: string;
  device_id: string;
  occurred_at: Date;
  synced: boolean;
}

class CheckinDB extends Dexie {
  tickets!: Table<Ticket, string>;
  pending_checkins!: Table<PendingCheckin, string>;

  constructor() {
    super('AriaCheckin');
    this.version(1).stores({
      tickets: 'id, code, event_id, state',
      pending_checkins: 'id, ticket_code, synced, occurred_at',
    });
  }
}

export const db = new CheckinDB();
```

### Ticket Snapshot Download (Online Phase)

```typescript
// apps/api/resources/js/Pages/Checkin/EventCheckin.tsx
import { db } from '@/lib/db';
import { useEffect, useState } from 'react';

export default function EventCheckin({ event }: { event: Event }) {
  const [downloadProgress, setDownloadProgress] = useState(0);
  const [isOfflineReady, setIsOfflineReady] = useState(false);

  useEffect(() => {
    downloadTickets();
  }, []);

  async function downloadTickets() {
    const response = await fetch(`/api/v1/events/${event.id}/tickets`, {
      headers: { Authorization: `Bearer ${authToken}` },
    });

    const { data: tickets } = await response.json();

    // Batch insert into IndexedDB
    await db.tickets.bulkPut(tickets.map((t: any) => ({
      id: t.id,
      code: t.code,
      event_id: event.id,
      ticket_type_name: t.ticket_type.name,
      owner_name: t.owner?.full_name || t.order.email,
      state: t.state,
      qr_signature: t.qr_signature,
      synced_at: new Date(),
    })));

    setIsOfflineReady(true);
  }

  return (
    <div>
      {isOfflineReady ? (
        <QRScanner onScan={handleScan} />
      ) : (
        <p>Downloading {downloadProgress}% of tickets...</p>
      )}
    </div>
  );
}
```

### QR Scan and Offline Check-in

```typescript
// apps/api/resources/js/Components/QRScanner.tsx
import { db } from '@/lib/db';
import { verifyTicketSignature } from '@aria/utils';
import { v4 as uuid } from 'uuid';

export function QRScanner({ onScan }: { onScan: (result: CheckinResult) => void }) {
  async function handleQRScanned(qrData: string) {
    // Step 1: Verify signature offline
    const { valid, code } = verifyTicketSignature(qrData);

    if (!valid) {
      return onScan({ status: 'invalid', message: 'Ticket signature invalid' });
    }

    // Step 2: Lookup ticket in IndexedDB
    const ticket = await db.tickets.where('code').equals(code).first();

    if (!ticket) {
      return onScan({ status: 'not_found', message: 'Ticket not found in snapshot' });
    }

    if (ticket.state === 'checked_in') {
      return onScan({ status: 'already_checked_in', ticket });
    }

    if (ticket.state === 'void') {
      return onScan({ status: 'void', message: 'Ticket voided (refund/cancel)' });
    }

    // Step 3: Mark as checked in locally
    ticket.state = 'checked_in';
    await db.tickets.put(ticket);

    // Step 4: Queue pending check-in for sync
    const deviceId = getDeviceFingerprint();
    await db.pending_checkins.add({
      id: uuid(),
      ticket_code: code,
      device_id: deviceId,
      occurred_at: new Date(),
      synced: false,
    });

    onScan({ status: 'success', ticket });

    // Step 5: Attempt background sync if online
    if (navigator.onLine) {
      syncPendingCheckins();
    }
  }

  return <div>{/* QR scanner UI using react-qr-reader */}</div>;
}
```

### Background Sync (Delta Upload)

```typescript
// apps/api/resources/js/lib/sync.ts
import { db } from './db';

export async function syncPendingCheckins() {
  const pending = await db.pending_checkins.where('synced').equals(false).toArray();

  if (pending.length === 0) return;

  try {
    const response = await fetch('/api/v1/checkins/batch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${authToken}` },
      body: JSON.stringify({ checkins: pending.map(p => ({ ticket_code: p.ticket_code, device_id: p.device_id, occurred_at: p.occurred_at })) }),
    });

    if (response.ok) {
      const { data: syncedIds } = await response.json();

      // Mark as synced
      await db.pending_checkins.where('id').anyOf(syncedIds).modify({ synced: true });

      // Re-download latest ticket states (conflict resolution)
      await refreshTicketStates();
    }
  } catch (error) {
    console.error('Sync failed, will retry later:', error);
  }
}

// Poll for sync every 30s when online
setInterval(() => {
  if (navigator.onLine) syncPendingCheckins();
}, 30000);
```

### Conflict Resolution (Server-side)

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\{Ticket, CheckinLog};
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function batchCheckin(Request $request)
    {
        $validated = $request->validate([
            'checkins' => 'required|array',
            'checkins.*.ticket_code' => 'required|exists:tickets,code',
            'checkins.*.device_id' => 'required|string',
            'checkins.*.occurred_at' => 'required|date',
        ]);

        $syncedIds = [];

        foreach ($validated['checkins'] as $checkin) {
            $ticket = Ticket::where('code', $checkin['ticket_code'])->firstOrFail();

            // Conflict resolution: first timestamp wins
            $existingCheckin = CheckinLog::where('ticket_id', $ticket->id)
                ->where('action', 'check_in')
                ->orderBy('occurred_at', 'asc')
                ->first();

            if ($existingCheckin && $existingCheckin->occurred_at < $checkin['occurred_at']) {
                // Server already has earlier check-in, skip this one
                continue;
            }

            // Create check-in log (idempotent: won't create duplicate)
            CheckinLog::firstOrCreate([
                'ticket_id' => $ticket->id,
                'device_id' => $checkin['device_id'],
            ], [
                'event_id' => $ticket->order->event_id,
                'action' => 'check_in',
                'occurred_at' => $checkin['occurred_at'],
            ]);

            $ticket->update(['state' => 'checked_in']);
            $syncedIds[] = $checkin['ticket_code'];
        }

        return response()->json(['data' => $syncedIds], 200);
    }
}
```

### Service Worker (Offline Caching)

```typescript
// apps/api/public/sw.js
const CACHE_NAME = 'aria-checkin-v1';
const urlsToCache = [
  '/checkin',
  '/css/app.css',
  '/js/app.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Cache-first strategy
      return response || fetch(event.request);
    })
  );
});

// Background sync API (when network restored)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-checkins') {
    event.waitUntil(syncPendingCheckins());
  }
});
```

## Consequences

### Positive
- **Offline-first UX**: Check-in works without internet for entire event duration
- **Fast scans**: <500ms from QR scan to validation (no network roundtrip)
- **Conflict-safe**: Server resolves duplicates using first-timestamp-wins rule
- **Cross-platform**: Works on iOS, Android, desktop without app stores
- **Security**: HMAC signatures prevent fake tickets even offline

### Negative
- **Storage limits**: IndexedDB quota (50MB) limits to ~10K tickets per event
- **iOS Safari quirks**: Service Worker support limited; must test thoroughly
- **Sync complexity**: Requires careful handling of network reconnection

### Risks and Mitigations

**Risk**: iOS Safari clears IndexedDB after 7 days of inactivity
**Mitigation**: Warn staff to re-download tickets if >7 days since last download; persist signing secret in encrypted localStorage

**Risk**: Multiple devices check in same ticket offline, both claim "first"
**Mitigation**: Server uses timestamp from checkin log; earlier timestamp wins; client re-syncs to show server truth

**Risk**: QR signature secret leaked, allows fake tickets
**Mitigation**: Rotate signing secret weekly; encrypt secret in IndexedDB using device-specific key; revoke old secrets post-rotation

## References
- DESIGN.md Section 11: Check-in PWA (Offline-first)
- Product Spec: aria-mvp.md User Story 3 (Staff performs offline check-in)
- External: [Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API), [IndexedDB](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API)
