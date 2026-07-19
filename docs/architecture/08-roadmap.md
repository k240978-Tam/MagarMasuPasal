# 8. Development Roadmap

Phased so that each phase ends with something demoable/testable, and later phases only ever
*add* modules rather than rework earlier ones — a direct consequence of the module boundaries
in [02](02-module-breakdown.md) and [07](07-folder-structure.md).

## Phase 0 — Foundation (platform skeleton, no business features yet)
- Laravel 12 project scaffold, `nwidart/laravel-modules`, Tailwind + Alpine build pipeline,
  dark mode toggle, CI (lint, PHPStan/Larastan, Pest/PHPUnit, module-boundary check).
- Tenancy module: `businesses`, `business_types` (seeded), `branches`, `TenantContext`,
  `BelongsToTenant` global scope, tenant resolution middleware.
- UserManagement: auth (Sanctum), Spatie Permission wired in, seeded roles
  (Owner/Manager/Cashier/Accountant/Inventory Manager/Admin), 2FA-ready schema.
- Settings module: business profile CRUD, `product_attribute_definitions` scaffold.
- AuditLog module: `audit_logs` table + a `Auditable` trait/listener wired to model events.
- Notification module: base infra (database channel), notification preferences.
- Base UI shell: authenticated layout, navigation, empty dashboard route.
- **Exit criteria:** an Owner can register the business, create a branch, invite a Manager and
  a Cashier with correct role-based access, and every write is captured in the audit log.

## Phase 1 — Catalog & Inventory Core
- Units, Categories, Products modules (including configurable attributes UI driven by
  `product_attribute_definitions`).
- Suppliers, Purchases (PO → goods receipt → `purchase_batches`).
- Inventory module: `inventory_stocks`, `stock_movements`, adjustments, low-stock detection job.
- CSV product import.
- **Exit criteria:** products can be created (with meat-shop attributes as one configured
  example among others), a purchase order can be received into stock, and stock levels update
  correctly with a full movement audit trail.

## Phase 2 — POS & Customer Display (the core selling loop)
- Laravel Reverb deployed; Echo wired on POS and Customer Display shells.
- POS module: cart state machine, barcode/search, weight entry, hold/resume, discounts, tax.
- Customer Display module: terminal pairing, live-synced cart view, idle promo rotation.
- CashRegister module: open/close session, cash movements, variance reporting.
- Sales module: sale finalization, receipt (DomPDF) + QR (Simple QRCode) printing, returns/void.
- PaymentManager module (interfaces + `cash` and `manual_qr` gateways only — no real gateway
  integration, per the explicit payment-architecture requirement).
- PWA shell + offline sale outbox for POS.
- **Exit criteria:** a full sale can be rung up on the Cashier POS, mirrored live on the
  Customer Display with zero polling, paid by cash, receipted, and correctly deducted from
  FIFO batch stock — including a hold/resume and a return, end to end.

## Phase 3 — Accounting
- Chart of Accounts seeded per business type; Journal Entries, General Ledger.
- Event listeners: `SaleCompleted`, `PurchaseReceived`, `ExpenseRecorded`,
  `CashSessionClosed` → auto-posted journal entries.
- Cash Book, Bank Book, Trial Balance, P&L, Balance Sheet, Bank Reconciliation.
- Expenses module (categorized, recurring-ready).
- **Exit criteria:** every sale/purchase/expense automatically produces a balanced journal
  entry, and Trial Balance/P&L/Balance Sheet reconcile against manually-verified test data.

## Phase 4 — Reports, Analytics, Dashboard
- Report catalog (all reports listed in the brief) with date/branch filters and PDF/CSV export.
- Nightly aggregation jobs (`daily_sales_aggregates`, `product_performance_aggregates`).
- Role-aware Dashboard with live tiles (Reverb-pushed metric updates), Chart.js visualizations.
- **Exit criteria:** Owner can answer "how did we do this month, what's selling, what's dead
  stock, when are we busiest" without leaving the dashboard/report screens.

## Phase 5 — Notifications, Backup, Settings Polish, Multi-Branch Depth
- Full notification set (low stock, daily summary, supplier/customer due, large-discount
  warning, failed login, backup completed) across database + email channels.
- Scheduled + manual backup, restore workflow, backup verification job.
- Stock transfers between branches; per-branch stock threshold overrides.
- Settings polish: tax rule engine, receipt template editor, feature toggles per tenant.
- **Exit criteria:** platform operates unattended for a week with correct alerts firing and a
  verified automatic backup/restore drill completed successfully.

## Phase 6 — Security Hardening & Compliance Pass
- Rate limiting tuned per route group, 2FA enabled end-to-end, encrypted-field audit,
  secure file upload review, dependency/security scan in CI, penetration-test pass on the
  Payment Manager and auth flows specifically.
- Formal review of tenant-isolation (automated tests asserting cross-tenant queries return
  zero rows for every module).
- **Exit criteria:** security review sign-off before the first real tenant goes live with real
  money moving through the system.

## Phase 7 — Public REST API Hardening for Future Clients
- OpenAPI spec finalized and published, API rate-limit tiers, API-key/token management UI for
  the Owner, versioning policy documented.
- Load testing (target: concurrent POS terminals across a projected multi-branch tenant, plus
  simulated thousands-of-tenants query load on shared tables) with index/query tuning as
  needed (`stock_movements`/`audit_logs` partitioning evaluated here if volume warrants it).
- **Exit criteria:** API is stable and documented enough for a future mobile app team to build
  against without backend changes.

## Phase 8 — Launch: Magar Masu Pasal Tatha Anya Tarkari
- Provision the tenant, select **Meat Shop** business type (weight-based units pre-selected,
  batch+expiry attributes on, credit customer group pre-configured), onboard the Halchowk
  branch, import initial product catalog, train staff, go live.
- Post-launch monitoring window with daily check-ins on error rates, queue backlogs, and
  cash-session reconciliation accuracy.

## Beyond Phase 8 (explicitly out of scope until requested)
- Real payment gateway plugins (eSewa, Khalti, FonePay, card processors) — architecture is
  ready (see [06-api-design.md §6.3](06-api-design.md#63-payment-manager-interfaces)), but no
  implementation ships until explicitly requested, per the brief.
- Additional business-type templates beyond the seeded defaults (grocery, pharmacy, etc.) —
  the attribute/unit system supports them already; each new template is configuration data,
  not new code.
- Native mobile apps consuming the Phase 7 API.

## Sizing note
Phases 0–2 form the minimum viable product for the first tenant to actually operate day-to-day
(sell, track stock). Phases 3–4 (Accounting/Reports) are what make it an *ERP* rather than a
POS and should not be skipped, but could run in parallel with Phase 2 stabilization if two
workstreams are available. Phases 5–7 are what make it safe and ready to onboard the *next*
thousand tenants, not strictly required for the first tenant's day-one launch — flagged in the
README as an open item on phase ordering.
