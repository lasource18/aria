# Eventbrite-for-West-Africa — Technical & Product Design

**Focus:** Côte d’Ivoire launch (Abidjan first), mobile-money native (Orange Money, MTN MoMo, Wave).  
**Goal:** Enable individuals & organizations to create, promote, and sell event tickets; attendees pay via mobile money; platform monetizes via per-ticket fees and paid account tiers.

---

## 1) Product Overview
**Core value:** Seamless ticketing & discovery with frictionless mobile-money checkout and fast, reliable payouts in XOF.

### Personas
- **Attendee**: discovers events, buys tickets (prefers mobile money + SMS e-tickets), stores passes offline.
- **Organizer** (single/SME): creates events, sets ticket types, receives payouts to mobile money or bank.
- **Venue/Promoter** (enterprise): multiple team members, branded pages, advanced analytics.
- **Platform Admin**: risk/fraud review, payout approval, customer support, fee configuration.

### MVP Scope
- Event creation & listing, ticket types (free/paid), basic discovery/search.
- Checkout with at least **1–2 mobile-money rails** (Orange Money, Wave) + card fallback (Stripe) if feasible.
- Ticket issuance (QR + alphanumeric code) via email + **SMS**.
- Organizer dashboard: sales, revenue, attendee list; manual & scheduled payouts.
- Check-in app (web/mobile PWA) with **offline mode**.

### Post-MVP
- Coupons, referral links, affiliate tracking, seating maps, waitlists, recurring events, subscriptions.
- Tiered features, organizer storefronts, API access for enterprise, white-label.

---

## 2) Monetization & Tiers
### Pricing Model
- **Per-ticket fee**: _platform_fee_pct_ + _platform_fee_fixed_ (XOF), configurable per country.
- **Payment processing fee**: pass-through from provider or absorbed (configurable).
- **Subscriptions** (paid tiers): monthly/annual; discounts on per-ticket fees and extra features.

### Account Tiers (initial proposal)
- **Basic (Free)**: unlimited free/paid events, standard page, standard analytics, standard support.
- **Pro**: reduced per-ticket fee, advanced analytics (sales funnels, cohorts, geo), promo tools (email blasts, SMS campaigns), custom branding, priority listing.
- **Enterprise**: multi-user org, SSO, white-label subdomain, API access, SLAs, invoicing & net settlement.

---

## 3) Non-Functional Requirements
- **Availability**: 99.9% uptime target; graceful degradation on payment-webhook delays.
- **Performance**: <200ms P95 for core read APIs in region; <3s P95 checkout initiation on mobile.
- **Security**: OWASP ASVS L2; hardened auth; PII & payment ref data protection; audit logs.
- **Compliance**: local data protection; KYC/KYB for organizers; tax invoice capabilities; anti-fraud.
- **Localization**: **fr-FR** default, en-US optional; currency **XOF**; time zone Africa/Abidjan.
- **Offline**: check-in app stores encrypted ticket snapshot; conflict-safe sync on reconnect.

---

## 4) High-Level Architecture
```mermaid
flowchart LR
  subgraph Client
    A[Attendee Web/App]-->B[Checkout]
    O[Organizer Dashboard]
    C[Check-in PWA]
  end
  subgraph Edge
    CDN[CDN / Cloudflare Pages]
  end
  subgraph Backend (Laravel + TS/Inertia)
    API[REST/GraphQL API]
    AUTH[Auth Service]
    EVT[Events Service]
    ORD[Orders/Cart Service]
    PAY[Payments Orchestrator]
    TIC[Tickets/QR Service]
    PYO[Payouts Service]
    ANA[Analytics/Events]
    ADM[Admin/Moderation]
    WEBH[Webhook Receiver]
    JOBS[Queue Workers]
  end
  subgraph Data
    PG[(PostgreSQL)]
    REDIS[(Redis)]
    OBJ[(S3-compatible storage)]
    WARE[Analytics DB/ClickHouse or PG OLAP]
    LOGS[(OpenSearch/Cloud Logging)]
  end
  subgraph Providers
    OMG[Orange Money]
    MTN[MTN MoMo]
    WAVE[Wave]
    STR[Stripe/Card Fallback]
    SMS[SMS Aggregator]
    MAIL[Email]
  end

  Client-->|HTTP/HTTPS|CDN-->API
  API-->AUTH & EVT & ORD & PAY & TIC & PYO & ANA & ADM
  API-->PG & REDIS
  JOBS-->PG & OBJ & Providers
  WEBH-->|Callbacks|PAY
  PAY<-->OMG & MTN & WAVE & STR
  TIC-->OBJ
  ADM-->LOGS
  ANA-->WARE
  API-->SMS & MAIL
```

**Key Principles**
- **Payments Orchestrator** abstracts providers; supports idempotency keys; reconciles via webhooks.
- **Order/Payment/Ticket state machines**; all transitions audited.
- **Queues** for email/SMS, ticket generation, payout batching, reconciliation.

---

## 5) Data Model (ERD + Core Tables)
```mermaid
erDiagram
  USER ||--o{ ORG_MEMBER : has
  ORG ||--o{ ORG_MEMBER : includes
  ORG ||--o{ EVENT : hosts
  EVENT ||--o{ TICKET_TYPE : offers
  EVENT ||--o{ ORDER : receives
  ORDER ||--|{ ORDER_ITEM : contains
  ORDER ||--o{ PAYMENT : paid_by
  ORDER ||--o{ TICKET : issues
  PAYMENT ||--o{ PAYOUT : settles_to
  ORG ||--o{ PAYOUT : receives
  EVENT ||--o{ CHECKIN_LOG : logs
  USER ||--o{ TICKET : owns

  USER {
    uuid id PK
    text email UNIQUE
    text phone
    text password_hash
    text full_name
    jsonb preferences
    text locale default 'fr-FR'
    timestamptz created_at
  }
  ORG {
    uuid id PK
    text name
    text country_code default 'CI'
    jsonb kyb_data
    text payout_channel(enum: orange_mo, mtn_momo, wave, bank)
    text payout_identifier
    boolean verified default false
    timestamptz created_at
  }
  ORG_MEMBER {
    uuid id PK
    uuid org_id FK
    uuid user_id FK
    text role(enum: owner, admin, staff, finance)
    timestamptz created_at
  }
  EVENT {
    uuid id PK
    uuid org_id FK
    text title
    text slug UNIQUE
    text description_md
    text category
    text venue_name
    text venue_address
    geography location
    timestamptz start_at
    timestamptz end_at
    text timezone default 'Africa/Abidjan'
    text status(enum: draft, published, canceled, ended)
    boolean is_online default false
    jsonb settings
    timestamptz created_at
  }
  TICKET_TYPE {
    uuid id PK
    uuid event_id FK
    text name
    text type(enum: free, paid, donation)
    numeric price_xof
    numeric fee_pass_through_pct
    int max_qty
    int per_order_limit
    timestamptz sales_start
    timestamptz sales_end
    boolean refundable default true
  }
  ORDER {
    uuid id PK
    uuid event_id FK
    uuid buyer_user_id FK NULLABLE
    text email
    text phone
    text currency default 'XOF'
    text state(enum: created, awaiting_payment, paid, canceled, expired, refunded)
    numeric amount_subtotal
    numeric amount_fees
    numeric amount_total
    text checkout_channel(enum: web, pwa, admin)
    text payment_provider(enum: orange_mo, mtn_momo, wave, stripe) NULLABLE
    text idempotency_key UNIQUE
    timestamptz expires_at
    timestamptz created_at
  }
  ORDER_ITEM {
    uuid id PK
    uuid order_id FK
    uuid ticket_type_id FK
    int qty
    numeric unit_price_xof
    numeric line_total
  }
  PAYMENT {
    uuid id PK
    uuid order_id FK
    text provider(enum: orange_mo, mtn_momo, wave, stripe)
    text provider_ref UNIQUE
    text state(enum: initiated, pending, succeeded, failed, canceled)
    jsonb request_payload
    jsonb webhook_payload
    timestamptz created_at
  }
  TICKET {
    uuid id PK
    uuid order_id FK
    uuid ticket_type_id FK
    uuid owner_user_id FK NULLABLE
    text code UNIQUE
    text qr_url
    text state(enum: issued, transferred, checked_in, void)
    timestamptz created_at
  }
  CHECKIN_LOG {
    uuid id PK
    uuid event_id FK
    uuid ticket_id FK
    uuid staff_user_id FK NULLABLE
    text action(enum: check_in, undo)
    text device_id
    timestamptz occurred_at
  }
  PAYOUT {
    uuid id PK
    uuid org_id FK
    text method(enum: orange_mo, mtn_momo, wave, bank)
    text destination
    numeric amount_xof
    text state(enum: queued, processing, sent, failed)
    timestamptz scheduled_for
    timestamptz created_at
  }
```

**Indexes & Constraints (selected)**
- `ORDER(expires_at)`, `ORDER(idempotency_key)` unique.
- `TICKET(code)` unique random (base32 length 10).
- `CHECKIN_LOG(event_id, occurred_at)`; `PAYMENT(provider_ref)` unique.
- Use partial indexes for `EVENT(status='published')` for search.

---

## 6) State Machines
### Order
- **created → awaiting_payment → paid → (refunded|canceled|expired)**
- Expiry via TTL (typically 10–15 minutes) to release ticket inventory.

### Payment
- **initiated → pending → (succeeded|failed|canceled)**
- Driven by provider callbacks (webhooks) + reconciliation retries.

### Ticket
- **issued → checked_in → (undo→issued)** or **void** (refund/chargeback).

---

## 7) Payments Orchestrator
**Abstraction**: `PaymentProvider` interface with drivers: `OrangeMoney`, `MTNMomo`, `Wave`, `Stripe`.

**Capabilities**
- Initiate collection (STK push/USSD/ref code), poll if needed, receive webhook, update `PAYMENT`/`ORDER` state.
- Idempotent `initiatePayment(order_id, amount, msisdn)` using `ORDER.idempotency_key`.
- Provider timeouts & retry/backoff; reconciliation cron.
- Fee model registry per provider/country.

**Webhooks**
- `POST /v1/webhooks/payments/{provider}` signed secrets; store raw payload; process async.
- Verify signature + replay protection (nonce, timestamp).

**Edge Cases**
- Payment success but webhook delayed → show pending, issue tickets only on confirmed success.
- Partial payments not supported (reject); duplicate callbacks handled idempotently.

---

## 8) Payouts & Settlement
- **Models:** `ORG` holds default payout channel + identifier (MSISDN/IBAN). Optional KYB verification before payouts.
- **Schedules:** T+1 by default; enterprise negotiated. Ability to **hold** payouts for risk flags.
- **Flow:** Aggregate `ORDER.amount_total` – fees – refunds → `available_balance`; batch `PAYOUT` jobs.
- **Reconciliation:** Import provider settlement files; match by `PAYMENT.provider_ref`.

---

## 9) APIs (v1) — representative selection
**Auth**
- `POST /v1/auth/register` `{email, phone, password, name, locale}`
- `POST /v1/auth/login` `{email|phone, password}` → `{access_token, refresh_token}`
- `POST /v1/auth/refresh`

**Organizations**
- `POST /v1/orgs` `{name, country_code, payout_channel, payout_identifier}`
- `GET /v1/orgs/{id}`
- `POST /v1/orgs/{id}/members` `{user_id, role}`

**Events**
- `POST /v1/orgs/{orgId}/events`
  - `{title, description_md, venue, start_at, end_at, timezone, is_online, settings}`
- `GET /v1/events?city=&from=&to=&category=&q=` (public)
- `PATCH /v1/events/{id}`; `POST /v1/events/{id}/publish`; `POST /v1/events/{id}/cancel`

**Ticket Types**
- `POST /v1/events/{id}/ticket-types` `{name, type, price_xof, max_qty, per_order_limit, sales_start, sales_end}`
- `GET /v1/events/{id}/ticket-types`

**Checkout / Orders**
- `POST /v1/events/{id}/checkout`
  - Body: `{items: [{ticket_type_id, qty}], buyer: {email, phone, user_id?}}`
  - Response: `{order_id, amount_total, expires_at}`
- `POST /v1/orders/{orderId}/pay` `{provider, msisdn}` → initiate mobile-money
- `GET /v1/orders/{orderId}` → state + payment links

**Tickets & Check-in**
- `GET /v1/events/{id}/attendees`
- `POST /v1/tickets/{code}/checkin` `{device_id}` (requires staff role)
- `POST /v1/tickets/{code}/undo`

**Payouts**
- `GET /v1/orgs/{id}/balance`
- `POST /v1/orgs/{id}/payouts` `{amount_xof, method?}` (admin rules apply)

**Webhooks**
- `POST /v1/webhooks/payments/orange-mo`
- `POST /v1/webhooks/payments/mtn-momo`
- `POST /v1/webhooks/payments/wave`

**Admin**
- `GET /v1/admin/risk/events?q=&flags=`
- `POST /v1/admin/orgs/{id}/verify`
- `POST /v1/admin/orders/{id}/refund` `{reason}`

**Errors & Conventions**
- JSON:API style; snake_case; `X-Idempotency-Key` header honored on write endpoints.
- Standard envelope: `{data, error:{code, message, details}}`.

---

## 10) Search & Discovery
- Postgres full-text + trigram indexes for titles/venues.
- Filters: date range, city (geospatial radius), category, price (free/paid), language.
- Ranking: recency, popularity (sales velocity), organizer tier (Pro boost), manual boosts.

---

## 11) Check-in PWA (Offline-first)
- **Tech:** React/Next PWA or Inertia page with Service Worker; IndexedDB for offline cache.
- **Flow:** Staff authenticates, selects event(s), downloads encrypted ticket snapshot (ticket code + state). On scan, mark locally; sync deltas on reconnect; conflict resolution: server wins if already checked-in.
- **Scanning:** QR contains `ticket:CODE` + short signature; device verifies signature offline.

---

## 12) Security & Risk
- **Auth:** JWT access + refresh; 2FA (SMS or TOTP) for organizers; passwordless login later.
- **RBAC:** `owner/admin/staff/finance` at org scope; least privilege.
- **Data:** PII encrypted at rest; phone numbers normalized (E.164). Secrets in KMS.
- **Webhooks:** HMAC signatures, replay protection, rotating secrets.
- **Anti-fraud:**
  - Organizer KYB/KYC on threshold (volume, refund rate, disputes).
  - Velocity checks: orders per MSISDN/IP/device.
  - Disposable email/phone heuristics; blacklists.
  - Chargeback/refund workflow; ticket voiding cascade.
- **Audit:** all state transitions + admin actions with who/when/why.

---

## 13) Observability & Ops
- **Logs:** structured JSON to OpenSearch/Cloud logs; PII scrubbing.
- **Metrics:** Prometheus-style counters (orders_created, payments_succeeded, webhooks_failed, checkins_per_minute); SLIs (latency, error rate).
- **Tracing:** OpenTelemetry (ingress → DB → provider calls).
- **Alerts:** on payment error spikes, webhook backlog, payout failures.

---

## 14) Localization & Currency
- Default **French** copy; i18n JSON catalogs; RTL not required.
- Currency **XOF** formatting; exchange-rate service for multi-currency displays if needed.
- Timezone handling per event; attendee local display.

---

## 15) Deployment & Environments
- **Frontend (attendee site + docs):** Cloudflare Pages.
- **Backend:** Containerized (Docker) on managed Kubernetes or Fly.io/Render; regional near Abidjan (or EU West) for latency.
- **DB:** Managed PostgreSQL; **Redis** for queues/sessions/rate limits; S3 for assets/QRs.
- **CI/CD:** GitHub Actions; migrations via Laravel; canary deploys for API.

**Env separation**: dev, staging, prod; separate providers’ sandbox credentials.

---

## 16) Tech Stack Choices
- **Backend:** Laravel 11 + PHP 8.3, Inertia.js + TypeScript for organizer UI; optional public Next.js site.
- **DB:** PostgreSQL 16; extensions: PostGIS (geography), pg_trgm, pgcrypto.
- **Cache/Queue:** Redis, Laravel Horizon.
- **Messaging:** Email (Mailersend/SES), SMS (local aggregator; Twilio fallback).
- **QR/Barcodes:** server-side generation to PNG/SVG stored in S3.

---

## 17) Background Jobs & Schedulers
- Ticket generation & email/SMS dispatch.
- Order expiry sweeper (reclaim inventory).
- Payment reconciliation (retry/pull status periodically).
- Payout batching & dispatch; failure retries and alerts.
- Analytics ETL to WARE nightly/hourly.

---

## 18) Analytics (MVP)
- **Facts:** `fact_orders`, `fact_tickets`, `fact_payments`, `fact_checkins`.
- **Dims:** `dim_event`, `dim_org`, `dim_date`, `dim_city`.
- Organizer dashboard powered by materialized views for quick reads.

---

## 19) Testing Strategy
- Unit tests for state machines; contract tests for payment providers (sandbox mocks).
- End-to-end Cypress for checkout & check-in flows.
- Load tests (k6) for ticket on-sale spikes.
- Chaos drills: webhook outage, Redis failure, delayed reconciliation.

---

## 20) Seed Data & Feature Flags
- Seed: example orgs, events, ticket types (free/paid), users.
- Flags: enable/disable provider; experimental features (coupons, seating).

---

## 21) Initial Backlog (MVP)
1. Auth & Org management with RBAC.
2. Event CRUD + publish/cancel; ticket types.
3. Checkout & Order state machine; inventory reservation & expiry.
4. Payments: Orange Money + Wave drivers; webhooks; reconciliation.
5. Ticket issuance (QR) + email/SMS; attendee portal.
6. Check-in PWA with offline cache & sync.
7. Organizer dashboard: sales & revenue.
8. Payouts T+1; admin review flow; KYB thresholds.
9. Search/discovery; public event pages; SEO basics.
10. Observability (logs/metrics/traces) & alerts.

---

## 22) Risk Register (selected)
- **Payment fragmentation:** Mitigate via provider abstraction; start with 1–2 providers.
- **Fraudulent organizers:** KYB, rolling reserves, manual review for high-risk.
- **Connectivity at venues:** Offline check-in + conflict-safe sync.
- **Regulatory changes:** Config-driven fee/tax; country packs.

---

## 23) Open Questions
- Do we escrow attendee funds until post-event or settle rolling T+1? (Default T+1; enterprise negotiable.)
- Refund policy defaults by event vs platform-wide?
- Card fallback (Stripe) in Côte d’Ivoire at launch or later?

---

## 24) Sample JSON Schemas (abridged)
**POST /v1/events/{id}/checkout** request
```json
{
  "items": [{"ticket_type_id": "uuid", "qty": 2}],
  "buyer": {"email": "a@b.c", "phone": "+2250700000000", "user_id": null},
  "locale": "fr-FR"
}
```
**Response**
```json
{
  "data": {
    "order_id": "uuid",
    "amount_subtotal": 10000,
    "amount_fees": 500,
    "amount_total": 10500,
    "currency": "XOF",
    "expires_at": "2025-11-09T12:01:00Z"
  }
}
```

**POST /v1/orders/{orderId}/pay** request
```json
{"provider": "orange_mo", "msisdn": "+2250700000000"}
```
**Webhook (Orange Money) example**
```json
{
  "event": "payment.succeeded",
  "ref": "OM-ABC123",
  "amount": 10500,
  "currency": "XOF",
  "order_id": "uuid",
  "timestamp": 1731150000,
  "sig": "hmacSHA256-base64"
}
```

---

## 25) UX Notes (MVP)
- Organizer flow: Create org → Create event → Add ticket types → Publish → Share link → See sales → Get payout.
- Attendee flow: Event page → Select tickets → Enter phone → STK push/USSD → Success → SMS ticket.
- Check-in: Staff scans QR; big "VALID"/"ALREADY USED" statuses; fallback manual entry.

---

## 26) Roadmap Highlights
- **Quarter 1:** MVP in Abidjan, Orange Money + Wave, 50–100 pilot events.
- **Quarter 2:** MTN MoMo, Pro tier, coupons & promos, improved analytics.
- **Quarter 3:** Mobile apps (attendee + organizer), seating, affiliate tracking.
- **Quarter 4:** Multi-country expansion pack, enterprise API & white-label.


---

## 27) Mobile App + Agentic Pipeline Alignment
This section aligns the Aria platform design with the new **agentic-app-pipeline-starter** tech stack (Expo RN + EAS + CI/CD + multi-agent flow) while preserving all backend and web architecture defined earlier.

### Mobile App Scope (Expo RN)
Aria will ship a cross‑platform mobile app for both **attendees** and **organizers** with the following flows:
- Attendee: browse events, buy tickets, store offline tickets, check purchase history.
- Organizer: event dashboard lite (stats, check‑in tools), live sales updates.
- Core offline-first check‑in experience implemented in mobile.

### Impact on Architecture
- A new mobile client will consume the same backend API.
- Authentication via token-based auth (JWT) with secure storage in Expo.
- Mobile-money checkout flow: deep links / webviews hitting backend payment endpoints.
- Offline ticket wallet: encrypted SQLite/AsyncStorage + background sync.
- Check‑in PWA remains, but mobile app adds a native alternative.

### CI/CD Alignment
Using the starter template:
- **apps/mobile/** contains the Expo client.
- **Preview builds** created via `preview.yml` map to staging environment.
- **Release builds** via EAS submit for iOS/Android.
- Mobile strings centralized for future i18n (FR default).

### Agent Roles
- **Planner:** generates specs inside `docs/product_specs/` including mobile screens.
- **Architect:** creates ADRs in `docs/adr/` for API–mobile contracts, offline strategy.
- **Implementer:** writes mobile code under `apps/mobile/` using React Query + Expo Router.
- **Reviewer:** checks code quality against reviewer_checklist.md.
- **QA:** validates critical mobile flows + offline mode per qa_checklist.md.

### Required Updates to Aria
- Add `/mobile` section to API spec describing endpoints used by mobile app.
- Add Expo deep linking scheme for payment callbacks.
- Add push notifications plan (Expo Notifications) for ticket delivery + event reminders.
- Add attendee offline-ticket-wallet encryption notes.
- Ensure privacy/permissions reflect mobile policies.

---

## 28) Competitive Analysis Integration
This section consolidates insights from West African ticketing competitors (Tikerama, Ayatickets, YALA, Tix.Africa, Demfati) and identifies differentiators incorporated into the platform design.

### Key Market Gaps Addressed
- **Tiered Pricing Model:** Competitors use flat fees (5–10%). Our design includes Free, Pro, and Enterprise tiers with differentiated features and reduced fees for Pro.
- **Fast Payouts:** Competitors provide slower or unclear payout schedules. We offer transparent T+1 or same‑day payouts with mobile‑money and bank options.
- **Offline‑First Check‑in:** No competitor emphasizes robust offline check‑in. Our PWA supports offline validation, encrypted local caching, and conflict‑safe sync.
- **Organizer Analytics:** Competitors expose limited analytics. Our design features sales funnel metrics, geo insights, buyer demographics, and exportable datasets.
- **Mobile‑Money Ecosystem:** We integrate directly with Orange Money, MTN MoMo, Wave, and support future provider expansion with a payment orchestration layer.
- **Diaspora Buyers & Multi‑Currency:** Competitors have limited international support. Our design supports card, PayPal, and multi‑currency buyers with settlement in XOF.
- **Marketing & Growth Tools:** Basic email/SMS tools are common. We provide campaigns, referral tracking, promo codes, and social share insights.
- **Secondary Market / Ticket Transfer:** Competitors generally ban resales. Our design includes optional, organizer‑controlled transfer and resale.
- **Feature‑Phone / USSD Support:** Ayatickets supports USSD; others less so. Our design includes optional USSD ticket purchasing for broader accessibility.

### Competitive Positioning Summary
Our platform differentiates through:
- **Operational excellence:** fast payouts, offline venue capability.
- **Financial inclusivity:** deep mobile‑money integration and multi‑rail payments.
- **Organizer empowerment:** advanced analytics, marketing tools, configurable tiers.
- **Regional scalability:** Côte d’Ivoire first, with multi‑country expansion planned.
- **Attendee experience:** personalized discovery, cross‑border payments, referral system.

### Design Implications
- Payment orchestrator must support mobile‑money payout APIs.
- Check‑in PWA must include offline cryptographic ticket verification.
- Organizer dashboard must emphasize analytics and reporting.
- Tiered features must be backend-enforced with RBAC and feature flags.
- Multi‑currency support requires currency conversion microservice.
- USSD gateway integration added to payment roadmap.

This ensures the product is purpose‑built for West Africa and distinctly superior to existing solutions.
