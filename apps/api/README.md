# Aria API

Laravel 11 REST API for the Aria event ticketing platform.

## Architecture

This API implements:
- **Authentication**: Laravel Sanctum token-based auth with email/phone login
- **Multi-tenant RBAC**: Organization-based access control with owner/admin/staff/finance roles
- **PostgreSQL Database**: UUID primary keys, proper indexing
- **RESTful Endpoints**: JSON:API compliant responses

## Requirements

- PHP 8.2+
- PostgreSQL 15+ (with PostGIS extension for Issue #3)
- Composer
- Laravel 11.x

## Setup

### 1. Install Dependencies

```bash
cd apps/api
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aria
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Start Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`.

## Testing

Run the test suite:

```bash
php artisan test
```

Run with coverage:

```bash
php artisan test --coverage
```

## API Endpoints

### Authentication (`/api/v1/auth`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/v1/auth/register` | Register new user | No |
| POST | `/v1/auth/login` | Login with email or phone | No |
| POST | `/v1/auth/refresh` | Refresh access token | Yes |
| POST | `/v1/auth/logout` | Logout and revoke token | Yes |

**Register Request:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "+22507123456",
  "password": "SecurePass123"
}
```

**Login Request (Email):**
```json
{
  "email": "john@example.com",
  "password": "SecurePass123"
}
```

**Login Request (Phone):**
```json
{
  "phone": "+22507123456",
  "password": "SecurePass123"
}
```

**Response:**
```json
{
  "data": {
    "user": {
      "id": "uuid",
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "+22507123456"
    },
    "tokens": {
      "access_token": "...",
      "refresh_token": "...",
      "expires_at": "2025-11-19T15:30:00Z"
    }
  }
}
```

### Organizations (`/api/v1/orgs`)

| Method | Endpoint | Description | Auth Required | Permission |
|--------|----------|-------------|---------------|------------|
| GET | `/v1/orgs` | List user's organizations | Yes | Member |
| POST | `/v1/orgs` | Create organization | Yes | Any user |
| GET | `/v1/orgs/{id}` | Get organization details | Yes | Member |
| PATCH | `/v1/orgs/{id}` | Update organization | Yes | Owner/Admin |
| POST | `/v1/orgs/{id}/members` | Add member | Yes | Owner/Admin |
| PATCH | `/v1/orgs/{id}/members/{userId}` | Update member role | Yes | Owner/Admin |
| DELETE | `/v1/orgs/{id}/members/{userId}` | Remove member | Yes | Owner/Admin |

**Create Organization:**
```json
{
  "name": "My Event Company",
  "country_code": "CI"
}
```

**Add Member:**
```json
{
  "user_id": "user-uuid",
  "role": "staff"
}
```

**Roles:**
- `owner`: Full control, can manage members and settings
- `admin`: Can manage events, ticket types, and view reports
- `staff`: Can manage event operations and check-ins
- `finance`: Read-only access to financial data

### Events (`/api/v1/events`) - Coming in Issue #3

Event creation, publishing, discovery with full-text search and geospatial queries.

## Data Models

### Users
- UUID primary key
- Email (unique) or phone (E.164 format)
- Password (bcrypt, cost 12)
- Full name, locale, preferences
- Platform admin flag

### Organizations (Orgs)
- UUID primary key
- Name, auto-generated slug (unique)
- Country code (ISO 3166-1)
- KYB data (JSON)
- Payout configuration
- Verification status

### Organization Members (OrgMembers)
- Belongs to Org and User
- Role: owner, admin, staff, finance
- Cannot remove last owner

## Security

### Authentication
- Token-based auth with Laravel Sanctum
- Access tokens: 15 minutes
- Refresh tokens: 30 days
- Password requirements: min 8 chars, mixed case, numbers

### Authorization
- Organization-scoped RBAC
- Policy-based authorization on all endpoints
- Role hierarchy: owner > admin > staff > finance

### Validation
- Phone numbers: E.164 format with regex
- Email: Standard Laravel validation
- UUIDs: Validated for all foreign keys

## Design Documents

- **DESIGN.md**: Full system design
- **ADR-0006**: Multi-tenant RBAC model
- **ADR-0009**: Authentication and session management
- **ADR-0004**: Database schema and migrations

## License

Proprietary - Aria Platform
