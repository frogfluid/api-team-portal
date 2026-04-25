# Team Samurai Plus — API Service

API service for the Team Samurai Plus iOS app. This is a standalone Laravel project that connects to the same database as the web portal, providing RESTful API endpoints with Sanctum token authentication.

## Architecture

```
Web Portal ↔ Database ↔ API Service ↔ iOS App
(session auth)            (Sanctum token auth)
```

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB credentials in .env (same database as web portal)
php artisan serve
```

## API Authentication

All endpoints (except `POST /api/auth/login`) require a Sanctum bearer token:

```
Authorization: Bearer <token>
```

## Key Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login, returns token |
| GET | `/api/dashboard/stats` | Dashboard statistics |
| GET | `/api/tasks` | List tasks |
| GET | `/api/work-schedules` | List work schedules |
| GET | `/api/leaves` | List leave requests |
| GET | `/api/daily-logs` | List daily logs |
| GET | `/api/weekly-reports` | List weekly reports |
| GET | `/api/messages` | List chat messages |
| GET | `/api/notifications` | List notifications |
| GET | `/api/payroll` | View payroll records |

## Schema Parity with Web

`api-team-portal` and `team-samuraiplus` (web) share the MySQL database
`team_samuraiplus`. Migration files are kept in sync between the two repos.

To verify parity:

    php artisan verify:schema

If new web migrations have been added, port them and reconcile:

    # 1. Copy the new migration files from team-samuraiplus/database/migrations/
    #    into api-team-portal/database/migrations/
    # 2. Reconcile history (marks already-applied as run, executes net-new):
    php artisan migrate:reconcile

`SCHEMA_PARITY_WEB_PATH` in .env overrides the default web path.

## Known limitations (Plan 02)

### Deletion sync gap

The five sync endpoints (`/api/notifications`, `/api/daily-logs`, `/api/weekly-reports`, `/api/work-schedules`, `/api/leaves`) and `/api/messages` and `/api/tasks` use `updated_at > updated_since` to filter. Records *deleted* between sync windows are not reported back to the client and may persist as stale entries in client-side caches.

**Mitigation:** clients should issue `?force_full=true` on:
- first launch after a cold start,
- post-logout/login,
- an explicit "pull to refresh" gesture,
- recovery from any error suspected of corrupting cache state.

A future plan introduces soft-delete + `/sync/tombstones` to close this gap.

### Error envelope

All API JSON responses for non-2xx statuses include an `error_code` field at the top level. v1 codes:

- `VALIDATION_ERROR` (422)
- `UNAUTHORIZED` (401)
- `FORBIDDEN` (403)
- `NOT_FOUND` (404)
- `PAYROLL_LOCKED` (409)
- `INTERNAL_ERROR` (500)

Clients should switch on `error_code` rather than `message`.

### Request tracing

Every API response carries an `X-Request-Id` header (UUID v4). When reporting issues, including this value lets the server log be located.
