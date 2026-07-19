# 6. API Design

## 6.1 Conventions

- **Base path:** `/api/v1/...` — version in the URL; breaking changes ship as `/api/v2` with
  the old version kept alive on a deprecation schedule.
- **Auth:** Laravel Sanctum. Web/PWA clients use Sanctum's stateful cookie auth (same-origin);
  future mobile apps use personal access tokens (`Authorization: Bearer ...`). Every token is
  scoped to exactly one `business_id` — there is no cross-tenant token.
- **Tenant/branch context:** derived from the authenticated principal, never from a client-sent
  header/body field (prevents tenant-spoofing). Branch selection (for multi-branch staff) is an
  explicit `X-Branch-Id` header, validated against the user's assigned branches server-side.
- **Response envelope:**
  ```json
  { "data": { ... }, "meta": { ... }, "links": { ... } }
  ```
  Errors:
  ```json
  { "message": "The given data was invalid.", "errors": { "field": ["reason"] } }
  ```
  using Laravel's standard `422`/`ApiResource`/`FormRequest` conventions — no bespoke error
  shape to relearn.
- **Pagination:** cursor-based on high-volume endpoints (`sales`, `stock_movements`,
  `audit_logs`) via `?cursor=...`; offset-based (`?page=`) on small reference lists
  (categories, units). Every list response includes `meta.per_page`, `meta.total` (where
  cheap to compute) or `meta.next_cursor`.
- **Idempotency:** state-changing POS endpoints (`POST /pos/sales`) require a client-generated
  `Idempotency-Key` (ULID) header — required for the offline-sync outbox described in
  [01-system-architecture.md](01-system-architecture.md#18-offline-tolerant-pos-pwa).
- **Rate limiting:** `throttle:api` default (60/min/user), tighter `throttle:auth` on
  login/password endpoints, a dedicated higher-ceiling `throttle:pos` bucket for the POS's
  higher request rate during active selling.
- **Every module's Service is reused by both the Blade controllers and the API controllers** —
  the API is a thin Resource/Controller layer over the same Services, so business logic is
  never duplicated or allowed to drift between "web" and "API" behavior.

## 6.2 Endpoint Groups (representative, not exhaustive)

| Group | Key Endpoints |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `POST /auth/2fa/challenge`, `GET /auth/me` |
| Businesses/Branches | `GET/PATCH /business`, `GET/POST/PATCH /branches`, `GET/POST /branches/{id}/terminals` |
| Users/Roles | `GET/POST/PATCH/DELETE /users`, `GET/POST /roles`, `PATCH /users/{id}/roles` |
| Products | `GET/POST/PATCH/DELETE /products`, `GET /products/search?q=`, `GET /products/barcode/{code}`, `POST /products/import` |
| Categories/Units | `GET/POST/PATCH/DELETE /categories`, `GET/POST/PATCH/DELETE /units` |
| Suppliers | `GET/POST/PATCH/DELETE /suppliers`, `GET /suppliers/{id}/dues` |
| Purchases | `GET/POST /purchase-orders`, `POST /purchase-orders/{id}/receive`, `PATCH /purchase-orders/{id}/cancel` |
| Inventory | `GET /inventory/stocks`, `POST /inventory/adjustments`, `POST /inventory/transfers`, `GET /inventory/movements`, `GET /inventory/low-stock` |
| Customers | `GET/POST/PATCH/DELETE /customers`, `GET/POST /customer-groups`, `GET /customers/{id}/dues` |
| POS | `GET /pos/terminals/{id}/state`, `POST /pos/cart/items`, `PATCH /pos/cart`, `POST /pos/cart/hold`, `POST /pos/cart/resume/{heldBillId}`, `POST /pos/sales` (idempotent, finalizes), `POST /pos/sales/{id}/void` |
| Sales | `GET /sales`, `GET /sales/{id}`, `POST /sales/{id}/returns`, `GET /sales/{id}/receipt.pdf` |
| Payments | see 6.3 below |
| Cash Sessions | `POST /cash-sessions/open`, `POST /cash-sessions/{id}/close`, `POST /cash-sessions/{id}/movements` |
| Expenses | `GET/POST/PATCH/DELETE /expenses`, `GET/POST /expense-categories` |
| Accounting | `GET /accounting/chart-of-accounts`, `POST /accounting/journal-entries` (manual entries), `GET /accounting/ledger`, `GET /accounting/trial-balance`, `GET /accounting/profit-and-loss`, `GET /accounting/balance-sheet`, `GET /accounting/cash-book` |
| Reports | `GET /reports/sales`, `GET /reports/inventory`, `GET /reports/profit`, `GET /reports/top-products`, `GET /reports/peak-hours`, `POST /reports/{key}/export` |
| Dashboard | `GET /dashboard/summary` |
| Notifications | `GET /notifications`, `PATCH /notifications/{id}/read` |
| Audit Logs | `GET /audit-logs` (filter by actor, action, date, entity) |
| Settings | `GET/PATCH /settings`, `GET/POST/PATCH /product-attribute-definitions` |
| Backup | `POST /backups`, `GET /backups`, `POST /backups/{id}/restore` |

## 6.3 Payment Manager Interfaces

The POS and Sales modules depend **only** on these contracts — never on a concrete gateway.
This is the design deliverable requested for the payment architecture; no gateway
implementation ships yet, only `cash` (no-op capture) and `manual_qr` (staff-confirmed capture)
as the two trivial built-in gateways.

```php
namespace App\Modules\PaymentManager\Contracts;

interface PaymentGatewayInterface
{
    public function key(): string; // 'cash', 'manual_qr', 'esewa', 'khalti', ...

    public function createPayment(CreatePaymentDTO $dto): PaymentResultDTO;

    public function verifyPayment(string $transactionPublicId): PaymentResultDTO;

    public function cancelPayment(string $transactionPublicId): PaymentResultDTO;

    public function refundPayment(RefundPaymentDTO $dto): PaymentResultDTO;

    public function getStatus(string $transactionPublicId): PaymentStatus; // enum

    public function handleWebhook(WebhookPayloadDTO $payload): PaymentResultDTO;
}

interface PaymentGatewayRegistryInterface
{
    public function resolve(string $gatewayKey): PaymentGatewayInterface;
    public function availableForBusiness(int $businessId): array; // gateways enabled per tenant
}
```

- `CreatePaymentDTO { amount, currency, payableType, payableId, gatewayKey, meta }`
- `PaymentResultDTO { transactionPublicId, status: PaymentStatus, gatewayReference, raw }`
- `PaymentStatus` enum: `Pending, Authorized, Captured, Failed, Cancelled, Refunded`
- Webhooks land on a **generic, gateway-keyed** route —
  `POST /api/v1/webhooks/payments/{gatewayKey}` — which does only signature verification
  (delegated to the resolved gateway's `handleWebhook`) and never contains gateway-specific
  branching outside that one class.
- Adding eSewa/Khalti/FonePay later = one new class implementing `PaymentGatewayInterface`,
  registered in `PaymentGatewayRegistry`, plus tenant-level config (API keys) in `settings`.
  **No POS, Sales, or Accounting code changes.**

## 6.4 Real-Time Channels (Reverb/Echo)

| Channel | Type | Payload | Consumers |
|---|---|---|---|
| `private-terminal.{terminalUuid}` | Private | `TerminalStateUpdated` (cart snapshot, status) | Cashier POS (own state confirm), Customer Display |
| `private-branch.{branchUuid}.notifications` | Private | `NotificationPushed` | Back-office staff for that branch |
| `private-business.{businessUuid}.dashboard` | Private | `DashboardMetricUpdated` (debounced) | Owner/Manager dashboard live tiles |

Channel authorization (`routes/channels.php`) checks the requesting user's `business_id` and
branch assignment before allowing a subscription — same tenant-isolation guarantee as the REST
API, enforced at the WebSocket layer too.

## 6.5 API Documentation

**As implemented (Phase 7):** hand-written OpenAPI 3.1 spec at [`docs/openapi.yaml`](../openapi.yaml),
covering the endpoints actually built — auth (login/logout/me), dashboard summary, products
(list/show/barcode), sales (list/show), reports/sales, notifications (list/mark-read),
customers (list/dues), and the POS finalize-sale endpoint. This is deliberately the
representative subset from §6.2, not every endpoint listed there.

Auto-generation from route/FormRequest annotations (`dedoc/scramble` or `knuckleswtf/scribe`)
was the original plan but wasn't used for this pass — pulled in as a dev dependency it would
hit the same GitHub-package-download restriction that blocked Larastan in Phase 6's CI work,
so hand-writing the spec was the reliable path. Worth revisiting once that's no longer a
constraint, so the spec is generated from source and can't drift from the routes it documents.

## 6.6 Versioning & Deprecation Policy

- **URL-versioned, not header-versioned:** `/api/v1/...` today; a breaking change ships as a
  new `/api/v2/...` prefix, never an in-place change to `v1`'s request/response shape. Additive,
  backward-compatible changes (a new optional field, a new endpoint) land in `v1` directly — no
  version bump needed for those.
- **What counts as breaking:** removing/renaming a field, changing a field's type or meaning,
  removing an endpoint, tightening validation on an existing field, changing an error's status
  code. Not breaking: adding a field, adding an endpoint, loosening validation, adding an
  optional query parameter.
- **Deprecation window:** once `v2` ships, `v1` is kept fully functional for a minimum of 6
  months, with `Sunset` and `Deprecation` response headers (RFC 8594 style) added to every `v1`
  response during that window so integrators get advance, machine-readable notice rather than a
  changelog they have to go read.
- **One tenant, one deployment:** because every business shares the same platform deployment
  (no per-tenant version pinning), a `v1` retirement date is announced to every Owner with an
  active API token (via the in-app Notification system this same phase built) at the start of
  the deprecation window, not just at cutover.
- **This spec's version tracks API compatibility, not release cadence** — `docs/openapi.yaml`'s
  `info.version` bumps only when `v1`'s contract changes, not on every deploy.

## 6.7 Index Review & Basic Load Check (Phase 7)

**Index review:** every `*_items`/`*_lines` line-item table was checked for indexes beyond its
primary key. `journal_entry_lines` already had `(business_id, account_id)`. Five tables had
none at all — a MySQL foreign key auto-indexes its own column, but SQLite doesn't, and neither
gives the composite `(business_id, <fk>)` pattern the app's actual queries need (e.g. Reports'
`GROUP BY product_id` within a business, or listing a document's lines). Added via dedicated
`add_indexes_to_*_table` migrations rather than editing the original create-table migrations:

| Table | Indexes added |
|---|---|
| `sale_items` | `(business_id, sale_id)`, `(business_id, product_id)` |
| `purchase_order_items` | `(business_id, purchase_order_id)`, `(business_id, product_id)` |
| `sale_return_items` | `(business_id, sale_return_id)`, `(business_id, sale_item_id)` |
| `stock_adjustment_items` | `(business_id, stock_adjustment_id)`, `(business_id, product_id)` |
| `stock_transfer_items` | `(business_id, stock_transfer_id)`, `(business_id, product_id)` |

`stock_movements`, `audit_logs`, `sales`, `notifications`, `purchase_batches`, and
`personal_access_tokens` were reviewed and already carry adequate composite indexes.

**Basic load check:** a modest smoke test (20 concurrent `curl` requests against
`GET /api/v1/dashboard/summary` and `GET /api/v1/products`, authenticated via a real Sanctum
token) returned all `200`s with no errors, timeouts, or tenant-scope leaks. This is explicitly
**not** a production load test — it ran against `php artisan serve`, which is single-threaded
and serializes requests, against a near-empty SQLite dataset. It's a sanity check that the new
v1 endpoints don't error or hang under light concurrency, not a capacity or throughput
benchmark. A real load test belongs in a staging environment behind PHP-FPM/Octane and MySQL,
once one exists.
