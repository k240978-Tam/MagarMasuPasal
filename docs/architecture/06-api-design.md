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

OpenAPI 3.1 spec generated from route/FormRequest/Resource annotations
(`dedoc/scramble` or `knuckleswtf/scribe` — evaluated at implementation time), published at
`/api/documentation`, kept in sync via CI check (spec regenerated and diffed on every PR that
touches `app/Modules/*/Http`).
