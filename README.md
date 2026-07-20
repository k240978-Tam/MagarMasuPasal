# Retail ERP for Nepal

A production-grade, multi-tenant Retail ERP — not a simple POS — built for
independent retailers in Nepal, starting with **Magar Masu Pasal Tatha
Anya Tarkari** (a meat shop, Halchowk, Kathmandu). It covers Point of
Sale, Inventory, Accounting, Purchasing, Suppliers, Customers, Reports,
Analytics, a live Customer Display, multi-branch operations, notifications,
and audit logging in one system.

**No business-type-specific logic is ever hardcoded.** Every business
(meat shop, grocery, pharmacy, restaurant, clothing store, ...) is
configuration data — units, product attributes, default customer groups,
receipt sections — against the same generic module code. See
[`Modules/Tenancy/database/seeders/BusinessTypeSeeder.php`](Modules/Tenancy/database/seeders/BusinessTypeSeeder.php)
for the current catalog of supported types.

## Documentation

| Doc | Covers |
|---|---|
| [`docs/architecture/01-system-architecture.md`](docs/architecture/01-system-architecture.md) | High-level architecture, offline-tolerant POS PWA |
| [`docs/architecture/02-module-breakdown.md`](docs/architecture/02-module-breakdown.md) | Every module and its responsibility |
| [`docs/architecture/03-database-schema.md`](docs/architecture/03-database-schema.md) | Schema design |
| [`docs/architecture/06-api-design.md`](docs/architecture/06-api-design.md) | Public REST API conventions, versioning policy |
| [`docs/architecture/08-roadmap.md`](docs/architecture/08-roadmap.md) | Phased build roadmap and exit criteria |
| [`docs/openapi.yaml`](docs/openapi.yaml) | OpenAPI 3.1 spec for the `/api/v1` surface |
| [`docs/operations/deployment.md`](docs/operations/deployment.md) | Production deployment runbook |
| [`docs/operations/go-live-checklist.md`](docs/operations/go-live-checklist.md) | Pre-launch checklist for a new tenant |
| [`docs/operations/post-launch-monitoring.md`](docs/operations/post-launch-monitoring.md) | Daily/weekly ops watch after launch |
| [`docs/onboarding/`](docs/onboarding/) | Role-based staff guides (Owner, Manager, Cashier) |

## Stack

Laravel 12 (PHP 8.3+), modularized with `nwidart/laravel-modules` — Clean
Architecture, Repository/Service patterns, DTOs, Form Requests, Events/
Listeners, Queues. Tailwind + Alpine.js frontend, Chart.js for dashboards,
Laravel Reverb + Echo for real-time POS↔Customer Display sync and live
dashboard tiles. Spatie Permission (roles/policies) and Media Library.
DomPDF for receipts/reports, Simple QRCode for receipt QR codes. MySQL in
production, Redis for cache/queue/sessions, Supervisor for process
management. Sanctum for both stateful web sessions and API tokens.

Payment processing goes through a generic, provider-independent Payment
Manager (`Modules/PaymentManager`) — only `cash` and staff-confirmed
`manual_qr` ship today; adding a real gateway (eSewa, Khalti, FonePay) is
one new class, no changes anywhere else, by design (see
[06-api-design.md §6.3](docs/architecture/06-api-design.md#63-payment-manager-interfaces)).

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # provisions platform reference data + the first tenant
npm run dev                  # Vite, in one terminal
php artisan serve            # in another
php artisan reverb:start     # for live POS/Customer Display sync
```

Seeded staff logins (all password `password` — change before any real
use): `owner@magarmasupasal.test`, `manager@magarmasupasal.test`,
`cashier@magarmasupasal.test`.

## Testing

```bash
php artisan test        # PHPUnit feature/unit suite
./vendor/bin/pint --test # style check
./vendor/bin/phpstan analyse # static analysis (see phpstan.neon.dist)
```

CI runs all of the above plus `composer audit`/`npm audit` on every push —
see `.github/workflows/`.
