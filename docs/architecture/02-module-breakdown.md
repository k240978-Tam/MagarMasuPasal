# 2. Module Breakdown

Each row is one `nwidart/laravel-modules` module under `Modules/`. "Depends on" lists modules
it consumes via public service interfaces or listens to via events — never direct model access.

## 2.1 Foundation Modules (Phase 0)

| Module | Responsibility | Key Entities | Depends on |
|---|---|---|---|
| **Tenancy** | Resolves the current business/branch context for every request; enforces the global tenant scope. | `businesses`, `business_types`, `branches`, `branch_terminals` | — |
| **UserManagement** | Authentication, staff accounts, roles/permissions (wraps Spatie Permission), 2FA. | `users`, `roles`, `permissions` (Spatie), `user_branch` | Tenancy |
| **Settings** | Business profile, configurable business-type attribute sets, tax rules, receipt templates, feature toggles per tenant. | `settings`, `business_profiles`, `product_attribute_definitions`, `tax_rules` | Tenancy |
| **AuditLog** | Immutable, queryable record of every sensitive action across all modules. | `audit_logs` | Tenancy, UserManagement |
| **Notification** | In-app + email/SMS-ready notification dispatch (low stock, dues, daily summary, security alerts). | `notifications` (Laravel default table), `notification_preferences` | Tenancy, UserManagement |
| **Backup** | Scheduled/manual DB + media backups, restore workflow. | `backup_runs` | Tenancy |

## 2.2 Catalog & Inventory Modules (Phase 1)

| Module | Responsibility | Key Entities | Depends on |
|---|---|---|---|
| **Units** | Configurable unit of measure per business type (kg, g, pcs, packet, dozen, L, mL, custom), unit conversion factors. | `units`, `unit_conversions` | Tenancy |
| **Categories** | Hierarchical, multi-category-per-product taxonomy. | `categories`, `product_category` (pivot) | Tenancy |
| **Products** | Product/catalog master data: pricing, barcode/SKU, images, batch/expiry flags, min/max stock, configurable attributes. | `products`, `product_attribute_values`, `product_prices` | Units, Categories, Settings |
| **Suppliers** | Supplier master data, contact/bank info, dues. | `suppliers`, `supplier_dues` | Tenancy |
| **Purchases** | Purchase orders, goods receipt, purchase batches (cost basis for FIFO). | `purchase_orders`, `purchase_order_items`, `purchase_receipts`, `purchase_batches` | Products, Suppliers |
| **Inventory** | Stock levels per branch/batch, adjustments, spoilage/waste/damage, branch transfers, valuation (FIFO), stock movement ledger, low-stock detection. | `inventory_stocks`, `stock_movements`, `stock_adjustments`, `stock_transfers` | Products, Purchases, Tenancy |

## 2.3 Sales & Customer-Facing Modules (Phase 2)

| Module | Responsibility | Key Entities | Depends on |
|---|---|---|---|
| **Customers** | Customer master data, customer groups (retail/credit/restaurant — configurable, not hardcoded), credit limits/dues. | `customers`, `customer_groups`, `customer_dues` | Tenancy |
| **POS** | Cart/session state machine, held bills, discounts, tax application, weight capture, terminal pairing, orchestrates Sales + PaymentManager + real-time broadcast. | `pos_sessions` (cart draft state), `held_bills` | Products, Inventory, Customers, PaymentManager, CustomerDisplay |
| **CustomerDisplay** | Broadcast contract + presentation state (promos/ads rotation) for the paired second screen. | `display_announcements` | POS (listens to `TerminalStateUpdated`) |
| **Sales** | Finalized sale records, line items, returns/refunds/voids, receipt/invoice generation. | `sales`, `sale_items`, `sale_payments`, `sale_returns` | POS, Inventory, PaymentManager, Accounting (event) |
| **PaymentManager** | Provider-independent payment lifecycle (create/verify/cancel/refund/status/webhook) behind a gateway-plugin interface. | `payment_transactions`, `payment_gateways` | Tenancy |
| **CashRegister** | Daily cash session open/close, expected vs. counted cash, over/short reporting. | `cash_sessions`, `cash_movements` | Sales, PaymentManager |

## 2.4 Finance Modules (Phase 3–4)

| Module | Responsibility | Key Entities | Depends on |
|---|---|---|---|
| **Expenses** | Expense entry, categorization, recurring expenses. | `expenses`, `expense_categories` | Tenancy, Accounting (event) |
| **Accounting** | Double-entry ledger: chart of accounts, journal entries (auto-posted from Sales/Purchases/Expenses events + manual entries), general ledger, cash/bank book, trial balance, P&L, balance sheet, bank reconciliation. | `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `bank_accounts`, `bank_reconciliations` | Listens to events from Sales, Purchases, Expenses, CashRegister |

## 2.5 Insight Modules (Phase 5)

| Module | Responsibility | Key Entities | Depends on |
|---|---|---|---|
| **Dashboard** | Role-aware landing page aggregating key metrics/widgets. | (read-model, no owned tables — queries via reporting repositories) | Reports |
| **Reports** | Pre-built report catalog (sales, inventory, purchases, profit, expense, cash, audit, top/slow movers, peak hours) with export (PDF/CSV). | `report_exports` (audit of generated reports) | Sales, Inventory, Purchases, Expenses, Accounting |
| **Analytics** | Trend/aggregation layer feeding dashboard charts; scheduled pre-aggregation jobs for heavy queries. | `daily_sales_aggregates`, `product_performance_aggregates` | Sales, Inventory |

## 2.6 Cross-Cutting / Infrastructure

| Module | Responsibility | Key Entities | Depends on |
|---|---|---|---|
| **Api** | Versioned REST API surface (Resources/Controllers) exposing every module to future clients. Thin — delegates to module services. | — | All business modules |
| **Broadcasting** (`app/Support`, not a business module) | Reverb/Echo channel authorization, shared event base classes. | — | — |

## 2.7 Why this decomposition

- **Vertical slices, not technical layers.** Each module is a full slice (its own DB tables →
  UI), so a team member can own "Purchases" end-to-end without touching "Accounting" files —
  matching the "each module independent" requirement.
- **Configurability lives in Settings/Units/Categories, not in code.** A pharmacy tenant turns
  on "batch + expiry required" and adds a "Prescription Required" attribute through Settings;
  a clothing tenant defines "Size"/"Color" attributes the same way. No module ships
  business-type-specific classes.
- **Accounting is event-driven, not called synchronously**, so Sales/Purchases/Expenses never
  import Accounting classes — keeping the dependency arrow one-directional and Accounting
  optional to disable for a tenant that doesn't want full bookkeeping.
