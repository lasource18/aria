# Aria Platform - MVP v0

## One-Liner
A mobile-money-native event ticketing platform for Côte d'Ivoire that enables seamless ticket sales with fast payouts, offline check-in, and frictionless mobile-money checkout.

## Target Users

### Attendee
Young urban professionals and students in Abidjan who:
- Prefer mobile money (Orange Money, Wave) over cards
- Need reliable SMS ticket delivery when email/data is unreliable
- Want to store tickets offline for venue entry
- Expect fast, simple checkout (3 taps or less)

### Organizer (Individual/SME)
Event creators, small promoters, and venue managers who:
- Need reliable, fast payouts (T+1) to mobile money wallets
- Want transparent fee structures with no hidden costs
- Require simple event creation and real-time sales tracking
- Need offline-capable check-in tools for venues with poor connectivity

### Platform Admin
Operations team members who:
- Monitor payment reconciliation and fraud signals
- Approve payouts and review KYB/KYC thresholds
- Support organizers and resolve disputes
- Configure fees and manage provider integrations

## MVP Scope

**Core Features Included:**

- **Event Creation & Management**: Organizers create events with basic details (title, description, venue, date/time), set ticket types (free/paid), and publish events with unique URLs
- **Ticket Types System**: Support free, paid, and donation tickets with configurable pricing (XOF), inventory limits, per-order limits, and sales windows
- **Public Event Discovery**: Browse and search published events by date, location (Abidjan), category, and price; mobile-optimized event detail pages
- **Mobile-Money Checkout**: Integrated checkout with Orange Money and Wave via STK push/USSD; order reservation with 10-minute expiry and inventory management
- **Ticket Issuance**: Automatic generation of QR codes + alphanumeric codes on payment success; delivery via email AND SMS with retry logic
- **Offline-First Check-In PWA**: Staff authenticates, downloads encrypted ticket snapshot, scans QR codes offline, and syncs check-ins when reconnected (conflict resolution: server wins)
- **Organizer Dashboard**: Real-time sales metrics, revenue tracking, attendee list export (CSV), ticket scan status, and event analytics
- **Payout System**: T+1 automated payouts to mobile money (Orange Money/Wave) or bank; admin review for flagged accounts; transparent balance tracking
- **Authentication & RBAC**: User registration/login, organization creation, multi-member teams with roles (owner/admin/staff/finance), and permission-based access control
- **Payment Orchestration**: Abstracted payment provider interface supporting Orange Money and Wave with webhook handling, idempotency, reconciliation, and retry logic

**Technology Stack:**
- Backend: Laravel 11 + PHP 8.3, PostgreSQL 16, Redis
- Frontend: Inertia.js + TypeScript for organizer UI
- Mobile: Expo React Native (attendee + organizer apps)
- Infrastructure: Managed PostgreSQL, Redis, S3-compatible storage
- Localization: French (fr-FR) default, XOF currency, Africa/Abidjan timezone

**Launch Scope:**
- Geographic focus: Abidjan, Côte d'Ivoire
- Payment rails: Orange Money + Wave (minimum 2 providers)
- Account tier: Basic (Free) tier only at launch
- Supported channels: Web + Mobile PWA

## Explicit Non-Goals

**Deferred to Post-MVP:**

- **Seating Maps & Reserved Seating**: All tickets are general admission; no seat selection, venue maps, or section assignments
- **Coupons, Promo Codes, Discounts**: No promotional pricing, referral codes, affiliate tracking, or discount campaigns
- **Recurring Events & Series**: Single events only; no multi-date events, recurring schedules, or event templates
- **API Access for Third Parties**: No public API, webhooks for organizers, or developer integrations
- **White-Label or Custom Domains**: No subdomain customization, organizer branding on checkout pages, or white-label options
- **Advanced Analytics & Reporting**: Beyond basic sales/revenue metrics; no funnel analysis, cohort tracking, or geo-heatmaps
- **Pro & Enterprise Tiers**: Only Basic (Free) tier available; tiered features and subscription billing deferred
- **Multi-Currency Support**: XOF only; no card payments (Stripe), PayPal, or international buyer flows
- **Ticket Transfers & Resale**: No attendee-to-attendee transfers, secondary marketplace, or name changes
- **Waitlists & Capacity Management**: No waitlist signup, automatic ticket release, or overselling protection
- **Advanced Marketing Tools**: No email campaigns, SMS blasts, or social media integrations
- **Mobile Native Apps (Launch)**: PWA only for MVP; native iOS/Android apps deferred to Q2-Q3

**Additional Constraints:**
- No USSD-only checkout flow (roadmap item)
- No multi-country support (Côte d'Ivoire only)
- No organizer storefronts or custom pages
- No video uploads or rich media galleries
- No live streaming or hybrid event features

## User Stories

### Story 1: Attendee discovers and purchases event ticket via mobile money
**As an** attendee in Abidjan
**I want to** discover local events, select tickets, and pay instantly with Orange Money or Wave
**So that** I receive my ticket via SMS within seconds and can attend the event

**Acceptance Criteria:**
- [ ] Attendee can browse published events filtered by date and location (Abidjan)
- [ ] Event detail page displays ticket types, prices (XOF), availability, and venue information
- [ ] Checkout flow collects phone number, initiates mobile-money STK push, and reserves tickets for 10 minutes
- [ ] On successful payment, attendee receives SMS with QR code link and alphanumeric ticket code within 60 seconds
- [ ] Ticket email is sent as backup with PDF attachment containing QR code
- [ ] Attendee can view ticket in mobile wallet (offline access) with QR code scannable at venue
- [ ] If payment fails or times out, order expires and inventory is released automatically

### Story 2: Organizer creates event, manages ticket types, and tracks sales
**As an** event organizer
**I want to** create an event, define multiple ticket types with pricing and limits, and view live sales data
**So that** I can manage my event efficiently and track revenue in real-time

**Acceptance Criteria:**
- [ ] Organizer can create organization account with KYB information and payout details (mobile money MSISDN)
- [ ] Organizer can create event with title, description, venue, start/end date/time, and category
- [ ] Organizer can add multiple ticket types (free/paid) with price (XOF), max quantity, per-order limit, and sales window
- [ ] Organizer can preview event page before publishing and generate shareable link on publish
- [ ] Dashboard displays real-time sales count, revenue (gross/net), tickets sold per type, and check-in status
- [ ] Organizer can export attendee list (CSV) with names, emails, phones, ticket types, and purchase dates
- [ ] Organizer can view available balance and initiate payout to mobile money wallet

### Story 3: Staff performs offline check-in at venue with poor connectivity
**As a** venue staff member with organizer credentials
**I want to** download event tickets before doors open and scan attendees offline
**So that** check-in continues seamlessly even when internet is unavailable

**Acceptance Criteria:**
- [ ] Staff authenticates via check-in PWA and selects event to work
- [ ] Staff downloads encrypted ticket snapshot (all ticket codes + current check-in status) to device
- [ ] Staff scans QR code and PWA validates ticket signature offline, showing "VALID" or "ALREADY CHECKED IN" status
- [ ] Check-in marks ticket locally in IndexedDB with timestamp and device ID
- [ ] When internet reconnects, PWA syncs check-in logs to server automatically
- [ ] If same ticket checked in on multiple devices offline, server resolves conflict (first check-in wins on sync)
- [ ] Staff can manually enter alphanumeric code as fallback if QR scan fails

### Story 4: Platform admin reviews payouts and monitors fraud signals
**As a** platform administrator
**I want to** review pending payouts, approve flagged accounts, and monitor payment reconciliation
**So that** organizers receive funds quickly while preventing fraud and ensuring financial integrity

**Acceptance Criteria:**
- [ ] Admin dashboard displays all pending payouts with organizer name, amount, payout method, and scheduled date
- [ ] Admin can view organizer KYB status, total sales volume, refund rate, and fraud flags
- [ ] Admin can approve or hold payouts with reason notes (logged in audit trail)
- [ ] Admin can view payment reconciliation status showing matched/unmatched provider transactions
- [ ] Admin receives alerts for payment webhook failures, high refund rates, or duplicate payment attempts
- [ ] Admin can manually initiate refund for disputed orders with reason and notification to buyer

### Story 5: System processes mobile-money payment and issues ticket atomically
**As the** platform payment system
**I want to** handle Orange Money and Wave payment webhooks reliably with idempotency and retry logic
**So that** attendees receive tickets immediately on successful payment without duplication or data loss

**Acceptance Criteria:**
- [ ] Payment initiation creates unique idempotency key and stores provider reference
- [ ] Webhook receiver validates HMAC signature, verifies timestamp for replay protection, and stores raw payload
- [ ] Payment state machine transitions from initiated → pending → succeeded/failed based on webhook data
- [ ] On payment success, order state updates to "paid" and ticket generation job dispatches automatically
- [ ] Ticket generation creates unique QR codes, stores in S3, and dispatches email + SMS jobs atomically
- [ ] If webhook arrives duplicate (same provider ref), system processes idempotently (no duplicate tickets)
- [ ] Reconciliation cron runs hourly to retry pending payments and sync provider settlement files

## Telemetry & Privacy

**Product Metrics (Tracked):**
- Order conversion rate (checkout initiated → payment succeeded)
- Payment success rate by provider (Orange Money vs Wave)
- Average checkout time (selection → payment confirmation)
- Ticket delivery success rate (SMS sent vs delivered)
- Check-in rate per event (tickets checked in / tickets sold)
- Payout processing time (order paid → payout sent)
- Event discovery funnel (browse → event view → ticket selection → checkout)

**Operational Metrics (Tracked):**
- Payment webhook latency and failure rate
- Check-in PWA offline sync success rate
- Database query performance (P95 latency for key endpoints)
- SMS/email delivery rates and retry counts
- Reconciliation match rate (payments vs provider settlements)

**Privacy Commitments:**
- **No PII collected beyond transactional necessity**: Only email, phone, name required for ticket delivery and event access
- **No third-party tracking pixels**: No Google Analytics, Facebook Pixel, or advertising SDKs in MVP
- **Data retention**: Order and payment data retained for 7 years (tax compliance); PII anonymized after 2 years unless required for dispute resolution
- **No cross-event attendee profiling**: Attendee purchase history not shared across organizers or used for targeting
- **Audit logs**: All admin actions (payout approvals, refunds, account flags) logged with user ID, timestamp, and reason

**Compliance:**
- Côte d'Ivoire data protection regulations followed (local storage preferred)
- Payment card data (if Stripe added) handled via PCI-compliant tokenization (no storage)
- Mobile money transaction references encrypted at rest; provider credentials in KMS

## Architecture Decision Records (ADRs)

The following ADRs provide detailed technical specifications for implementing this MVP:

**Core Architecture:**
- [ADR-0002: Payment Orchestrator Abstraction Layer](../adr/0002-payment-orchestrator-abstraction.md) - Addresses Issue #1
- [ADR-0003: Monorepo Structure and Platform Architecture](../adr/0003-monorepo-structure-platform-architecture.md)
- [ADR-0004: Database Schema and Migrations Strategy](../adr/0004-database-schema-migrations-strategy.md)
- [ADR-0005: State Machines for Orders, Payments, and Tickets](../adr/0005-state-machines-orders-payments-tickets.md)
- [ADR-0006: Multi-Tenant RBAC Model](../adr/0006-multi-tenant-rbac-model.md)

**Application Layer:**
- [ADR-0007: Offline-First Check-in PWA Architecture](../adr/0007-offline-first-checkin-pwa-architecture.md)
- [ADR-0008: REST API Design Standards](../adr/0008-rest-api-design-standards.md)
- [ADR-0009: Authentication and Session Management](../adr/0009-authentication-session-management.md)

**Infrastructure:**
- [ADR-0010: Deployment Architecture and Environments](../adr/0010-deployment-architecture-environments.md)
- [ADR-0011: Background Jobs and Queue Architecture](../adr/0011-background-jobs-queue-architecture.md)
- [ADR-0012: Observability and Monitoring Stack](../adr/0012-observability-monitoring-stack.md)

**Security & Compliance:**
- [ADR-0013: Data Protection and Privacy](../adr/0013-data-protection-privacy.md)
- [ADR-0014: Payment Security and Anti-Fraud](../adr/0014-payment-security-anti-fraud.md)

See [docs/adr/README.md](../adr/README.md) for complete ADR index and cross-references.

## Related Issues

**Architecture:**
- #1 - [Arch] Design payment orchestrator abstraction layer (Completed via ADR-0002)

**Implementation:**
- #2 - Implement authentication and organization RBAC system
- #3 - Build event CRUD and ticket types management system
- #4 - Implement checkout flow and order state machine with inventory management
- #5 - Build payments orchestrator with Orange Money and Wave integration
- #6 - Implement ticket generation with QR codes and SMS/email delivery
- #7 - Build check-in PWA with offline mode and conflict-safe sync
- #8 - Build organizer dashboard with sales and revenue analytics

**Reference:**
- Design Document: `/Users/claude-micaelguinan/aria/context/DESIGN.md`
- Sections: 1 (Product Overview), 5 (Data Model), 6 (State Machines), 7 (Payments), 11 (Check-in), 18 (Analytics), 21 (Initial Backlog)
