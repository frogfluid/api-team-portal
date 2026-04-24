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
