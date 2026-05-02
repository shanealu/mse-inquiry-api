# MSE Inquiry API

A Laravel REST API that captures, stores, and retrieves customer inquiries for the **Maldives Stock Exchange (MSE)** website. Every write and every single-record read is recorded in an immutable audit log so the system supports compliance, auditing, and support follow-up.

## Stack

- **Framework:** Laravel 13.x
- **PHP:** 8.4
- **Database:** MySQL 8.0+ (PostgreSQL 15+ also supported)
- **Validation:** Form Requests
- **Response:** API Resources
- **Testing:** Pest 4 (SQLite in-memory)

## Prerequisites

- PHP 8.4
- Composer
- MySQL 8.0+ or PostgreSQL 15+

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan install:api          # only if routes/api.php does not yet exist
php artisan migrate
php artisan serve
```

The API is then available at `http://localhost:8000/api/v1`. If you're on Laravel Herd, it's available at `https://mse-inquiry-api.test/api/v1`.

## Running Tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`).

## Endpoints

All endpoints live under `/api/v1`, return JSON, and are throttled at **60 requests per minute per IP**.

### `POST /api/v1/inquiries` — Create inquiry

```bash
curl -X POST http://localhost:8000/api/v1/inquiries \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "trading",
    "subject": "Question about T+2 settlement",
    "message": "I would like to understand how settlement works for cross-listed shares.",
    "name": "Ahmed Shaneel",
    "email": "ahmed@example.com",
    "phone": "+9607123456"
  }'
```

**201 Created**

```json
{
  "data": {
    "id": 1,
    "reference_number": "INQ-2026-000001",
    "type": "trading",
    "status": "new",
    "subject": "Question about T+2 settlement",
    "submitted_at": "2026-05-02T10:15:30Z"
  }
}
```

**Validation error (422):**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Please provide a valid email address."],
    "type":  ["The inquiry type must be one of: trading, market_data, technical_issue, general_question."]
  }
}
```

`ip_address`, `user_agent`, and `submitted_at` are captured server-side; values sent in the request body for those fields are ignored. `status` always starts at `new` regardless of what the client sends.

### `GET /api/v1/inquiries` — List inquiries

```bash
# Basic list
curl "http://localhost:8000/api/v1/inquiries" -H "Accept: application/json"

# Filter by type and status
curl "http://localhost:8000/api/v1/inquiries?type=trading&status=new" -H "Accept: application/json"

# Filter by submitter email (used by support follow-up)
curl "http://localhost:8000/api/v1/inquiries?email=ahmed@example.com" -H "Accept: application/json"

# Date range + pagination + sort
curl "http://localhost:8000/api/v1/inquiries?from=2026-04-01&to=2026-04-30&per_page=25&sort=-submitted_at" \
  -H "Accept: application/json"
```

**Query parameters:**

| Param      | Type    | Default      | Notes                                                    |
|------------|---------|--------------|----------------------------------------------------------|
| `type`     | string  | —            | `trading`, `market_data`, `technical_issue`, `general_question` |
| `status`   | string  | —            | `new`, `in_progress`, `resolved`, `closed`               |
| `email`    | string  | —            | Exact match                                              |
| `from`     | date    | —            | `created_at >= from`                                     |
| `to`       | date    | —            | `created_at <= to`                                       |
| `per_page` | int     | 15           | Max 100                                                  |
| `page`     | int     | 1            |                                                          |
| `sort`     | string  | `-created_at`| `-` prefix = desc; `created_at` or `submitted_at`        |

Unknown query parameters return **422** so client bugs surface early. The list response uses a slim summary resource that omits the full `message` body to keep payloads compact.

### `GET /api/v1/inquiries/{idOrRef}` — Get one inquiry

Resolves either by numeric `id` or by `reference_number` — convenient when support staff have a reference number from a follow-up email.

```bash
curl http://localhost:8000/api/v1/inquiries/1 -H "Accept: application/json"
curl http://localhost:8000/api/v1/inquiries/INQ-2026-000001 -H "Accept: application/json"
```

Each successful read writes an audit row with `action = 'viewed'` (the listing endpoint deliberately does **not** audit, otherwise admin browsing would flood the table). Soft-deleted inquiries return **404**.

## Architecture Notes

### Layered architecture

```
Routes → Controller (thin) → Form Request (validation) → Service (business logic, transactions) → Eloquent → DB
                                                                ↓
                                                          API Resource (response shape)
```

Controllers handle HTTP only. Validation lives in Form Requests. Business logic, transactions, and side effects (like audit writes) live in services. The response shape is decoupled from the database via API Resources, so column changes don't ripple into clients.

The Repository pattern is deliberately omitted — Eloquent already abstracts the database enough for this scope, and adding repositories would be over-engineering against the brief's "keep it simple" direction.

### Service layer & transactions

`InquiryService::store()` wraps three operations in a single `DB::transaction`:

1. Insert the inquiry row (`reference_number` starts as `NULL`).
2. Compute the reference number from the just-assigned auto-increment `id` (`INQ-{YEAR}-{6-digit zero-padded id}`) and persist it.
3. Write the `created` audit row.

If any step throws, all three roll back atomically. The audit logger throwing is covered by a feature test that asserts no inquiry persists.

The `reference_number` column is **nullable + unique** because the value is derived from the row's own auto-increment `id`. The transaction guarantees no other connection ever observes a row with a `NULL` reference. If MSE later wants per-year reset (`INQ-2026-000001` → `INQ-2027-000001`), switch to a `counters` table with a row-level lock — a comment in `InquiryService` flags this trade-off.

### Audit logging

`AuditLogger` writes to a separate `inquiry_audit_logs` table with no `updated_at` — audit rows are immutable. Every meaningful event creates a row:

- `created` on `POST /inquiries`
- `viewed` on `GET /inquiries/{id}` (only on successful resolution; 404s do not write a row)
- Future: `status_changed` with old/new values in the JSON `context` column

Listing inquiries is treated as an admin browsing activity and is **not** audited; logging every list call would bloat the table without compliance value.

### Validation strategy

Defense in depth — three layers, each with a distinct failure mode:

1. **Form Requests** (primary): declarative rules + custom user-facing messages.
2. **Database constraints** (last line): `NOT NULL`, length caps, unique on `reference_number`.
3. **Enum casts on the model**: invalid type/status values cannot be persisted programmatically.

Filter validation lives in `ListInquiriesRequest`. Unknown query params return 422 (rather than being silently ignored) so client bugs are visible.

### Error handling

`bootstrap/app.php` customizes JSON rendering for `/api/*`:

- `ModelNotFoundException` → 404 `{"message": "Inquiry not found."}`
- `NotFoundHttpException` (route binder miss) → 404
- Other unhandled exceptions → 500 with a generic message; the full stack trace is logged via Laravel's default channel
- Validation and HTTP exceptions pass through to Laravel's standard handlers

Internal details (table names, SQL, file paths) never leak into API responses.

### Testing

24 Pest tests covering happy paths, every validation case (table-driven), the transaction rollback when the audit logger throws, list pagination + filters, both ID and reference resolution, and 404 paths (unknown id, unknown reference, soft-deleted). Tests use SQLite in-memory and `RefreshDatabase` for isolation.

## Possible Enhancements

These were deliberately left out per the "keep it simple" direction in the brief, but would be the next steps for a production deployment:

- **Authentication / authorization** — Sanctum or Passport for an admin-facing API; role-based policies for status changes.
- **Admin UI / frontend** — currently backend-only.
- **Email notifications** — send to the support team on inquiry creation; auto-acknowledge to the submitter.
- **File attachments** — relevant for technical-issue inquiries (screenshots, logs).
- **Multi-language support** — translate validation messages and acknowledgement emails.
- **Per-year reference number reset** — switch the global-id-based scheme to a `counters` table with row-level locking.
- **Real phone validation** — current regex is a format check; integrate `libphonenumber` for dial-able verification.
- **Throttling tuning** — 60/min/IP works for individual users but is tight for shared NAT / corporate networks; consider per-email or per-session limits in addition.
