# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
php artisan test

# Run a single test file
php artisan test tests/Feature/Media/MediaSortTest.php

# Run a specific test method
php artisan test --filter=test_method_name

# Run tests excluding LDAP/integration (requires Docker)
php artisan test --exclude-group ldap,integration

# Lint (PSR-12 via Pint)
./vendor/bin/pint

# Static analysis (level 5)
./vendor/bin/phpstan analyse

# Check for security vulnerabilities
composer audit

# Compile assets (dev)
npm run dev

# Compile assets (production)
npm run build

# Run all migrations (platform + tenant)
php artisan migrate --path=database/migrations/platform
php artisan migrate --path=database/migrations/tenant --database=tenant

# Run scheduled commands manually
php artisan nas:sync
php artisan nas:sync --deep
php artisan pladigit:sync-ldap
php artisan media:refresh-exif
php artisan pladigit:generate-recurring-tasks
```

## Architecture Overview

### Multi-Tenancy (Custom, Not a Package)

Tenancy is custom-built with **two separate databases**:
- **Platform DB** (`mysql` connection): `organizations` table, audit logs, SMTP/modules config
- **Tenant DB** (`tenant` connection): dynamically reconfigured per request by `ResolveTenant` middleware

The `ResolveTenant` middleware is prepended to the pipeline and runs first for every request. It extracts the organization slug from the subdomain, loads the `Organization` from the platform DB, then calls `TenantManager::connectTo(organization)` to reconfigure the tenant DB connection.

`TenantManager` is a singleton (`app/Services/TenantManager.php`). Access the current organization with `TenantManager::current()` or `TenantManager::currentOrFail()`.

### Database Migrations

Migrations are split into two directories — always specify which path:
- `database/migrations/platform/` — organizations, modules, SMTP
- `database/migrations/tenant/` — all tenant data (users, projects, media, etc.)

### Authentication & Guards

Three guards defined in `config/auth.php`:
- `web` / `tenant`: session-based for tenant users
- `super-admin`: session-based, credentials from `.env` only (no DB)
- `api`: Sanctum (future)

Super-admin lives entirely outside tenant scope. CSRF is exempted only for `/login` to support cross-domain tenant login.

### Authorization (Dual-Layer)

- **`UserRole` enum** — global role per tenant user (Admin, Président, DGS, Resp.Direction, Resp.Service, Agent)
- **`ProjectRole` enum** — per-project role (Manager, Member, Viewer)
- Policies: `ProjectPolicy`, `TaskPolicy`, `MediaAlbumPolicy`, `MediaItemPolicy` (registered in `AppServiceProvider`)

### Feature Modules

Modules are enabled per organization via `enabled_modules` JSON column on `organizations`. The `RequireModule` middleware (`module` alias) gates route groups. Available modules: `MEDIA`, `PROJECTS`, `GED`, `COLLABORA`, `CHAT`, `CALENDAR`, `SURVEY`, `ERP`, `RSS`.

### Routes Structure

- `routes/web.php` — all tenant + super-admin routes
- `routes/projects.php` — project sub-routes (imported inside web.php)
- `routes/console.php` — scheduled commands (cron definitions)
- `routes/api.php` — minimal, future use

### Media / NAS

Media management uses a driver abstraction (`NasManager`) supporting three drivers: `local`, `sftp`, `smb` (configured in `config/nas.php`). `MediaService` handles upload, SHA-256 deduplication, EXIF extraction, watermarking. Jobs `ProcessMediaUpload` and `ProcessZipImport` run on the `database` queue.

### Testing

Tests use **two real databases** (`pladigit_testing_platform` and `pladigit_testing_tenant`) — no mocks. `tests/TestCase.php` runs migrations once per session (cached statically) and truncates tables between tests. LDAP and integration tests require Docker (`docker/docker-compose.test.yml`) and are excluded from CI via `--exclude-group ldap,integration`.

The test organization fixture always has modules `['media', 'projects']` enabled.

### Key Config Files

| Config | Purpose |
|--------|---------|
| `config/database.php` | Platform + tenant DB connections |
| `config/nas.php` | NAS driver, allowed MIME types, max 200 MB |
| `config/ldap.php` | LDAP/AD with circuit breaker, LDAPS required |
| `config/superadmin.php` | Super-admin credentials (from `.env`) |
| `config/google2fa.php` | TOTP configuration |

### Code Quality Standards

- PSR-12 (enforced by Pint on every commit via CI)
- PHPStan level 5 (0 errors required)
- `BCRYPT_ROUNDS=12` in production, `4` in tests
- Soft deletes on projects, tasks, media items
- Audit logging on all tenant changes via `AuditService`
