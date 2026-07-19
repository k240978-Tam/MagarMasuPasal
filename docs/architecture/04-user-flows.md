# 4. User Flow Diagrams

## 4.1 Tenant Onboarding

```mermaid
flowchart TD
    A["Owner signs up"] --> B["Create business + select Business Type\n(e.g. Meat Shop)"]
    B --> C["Business Type seeds defaults:\nunits, attribute set, chart of accounts,\nreceipt template"]
    C --> D["Create first branch (Halchowk)"]
    D --> E["Invite staff, assign roles\n(Owner/Manager/Cashier/...)"]
    E --> F["Configure categories, units, tax rules\n(override defaults as needed)"]
    F --> G["Add products\n(manually or via CSV import)"]
    G --> H["Pair Customer Display to a terminal\n(scan pairing QR)"]
    H --> I["Open first cash session"]
    I --> J["Ready to sell"]
```

## 4.2 Cashier POS Sale (core loop)

```mermaid
sequenceDiagram
    actor Cashier
    participant POS as Cashier POS
    participant SVC as POS Service
    participant CD as Customer Display
    participant INV as Inventory Module
    participant PAY as Payment Manager

    Cashier->>POS: Open register / start session
    POS->>SVC: OpenCashSession
    Cashier->>POS: Search/scan product (barcode/name)
    POS->>SVC: AddCartItem(product, qty/weight)
    SVC-->>POS: Updated cart draft
    SVC-->>CD: Broadcast TerminalStateUpdated (via Reverb)
    Note over CD: Shows item, qty, price, running total instantly
    Cashier->>POS: Apply discount / select customer
    POS->>SVC: UpdateCart(discount, customer)
    SVC-->>CD: Broadcast TerminalStateUpdated
    Cashier->>POS: Choose payment (cash/QR/mixed)
    POS->>PAY: CreatePayment(amount, method)
    PAY-->>POS: PaymentStatus(pending/captured)
    SVC-->>CD: Broadcast "Waiting for Payment"
    PAY-->>SVC: PaymentCaptured event
    SVC->>SVC: FinalizeSale (DB transaction)
    SVC->>INV: Deduct stock (FIFO batch consumption)
    SVC-->>CD: Broadcast "Payment Successful / Thank You"
    SVC-->>POS: Sale completed, print receipt
    Note over SVC: Fires SaleCompleted event → Accounting, Audit, Notification listeners (queued)
```

## 4.3 Hold / Resume Bill

```mermaid
flowchart LR
    A["Cart in progress"] -->|Hold| B["Snapshot cart → held_bills"]
    B --> C["Cashier serves next customer"]
    C -->|Resume| D["Load held_bills snapshot back into cart"]
    D --> E["Continue as normal sale"]
    B -->|Abandoned past policy window| F["Auto-expire / manager cancels"]
```

## 4.4 Return / Refund / Void

```mermaid
flowchart TD
    A["Cashier/Manager opens completed sale"] --> B{Within return policy window?}
    B -->|No| C["Manager override required\n(policy exception, audited)"]
    B -->|Yes| D["Select line items + quantity to return"]
    C --> D
    D --> E["Choose refund method\n(cash / original payment / store credit)"]
    E --> F["Sale Service: create sale_return + return items"]
    F --> G["Inventory: stock_movements +qty (sale_return)"]
    F --> H["Payment Manager: RefundPayment"]
    F --> I["Accounting: reversing journal entry (event)"]
    F --> J["Audit log: sale.refunded"]
```

## 4.5 Purchasing → Inventory (Goods Receipt, FIFO batch creation)

```mermaid
sequenceDiagram
    actor Manager
    participant PUR as Purchases Module
    participant SUP as Supplier
    participant INV as Inventory Module
    participant ACC as Accounting Module

    Manager->>PUR: Create Purchase Order (products, qty, expected cost)
    PUR->>SUP: (reference only, no external integration yet)
    Manager->>PUR: Mark goods received (full or partial)
    PUR->>INV: Create purchase_batch(es) with unit_cost, expiry
    INV->>INV: stock_movements(type=purchase_receipt), update inventory_stocks
    PUR-->>ACC: PurchaseReceived event
    ACC->>ACC: Post journal entry (Dr Inventory Asset, Cr Accounts Payable)
    Note over PUR: Partial receipt keeps PO status = partially_received
```

## 4.6 Low Stock Alert → Reorder

```mermaid
flowchart LR
    A["Scheduled job: low-stock sweep\n(hourly)"] --> B{quantity_on_hand <= min_stock?}
    B -->|Yes| C["Fire LowStockDetected event"]
    C --> D["Notification module: notify Owner/Manager/Inventory Manager"]
    D --> E["Manager reviews Inventory > Low Stock report"]
    E --> F["Create Purchase Order from suggested reorder list"]
    B -->|No| G["No action"]
```

## 4.7 Daily Cash Closing

```mermaid
flowchart TD
    A["Cashier: Close Register"] --> B["System computes expected cash\n(opening + cash sales + paid_in - paid_out - refunds)"]
    B --> C["Cashier counts drawer, enters actual cash"]
    C --> D["System computes variance"]
    D --> E{Variance beyond tolerance?}
    E -->|Yes| F["Flag for Manager review + audit log"]
    E -->|No| G["Cash session closed"]
    F --> G
    G --> H["Daily Sales Summary notification queued"]
```

## 4.8 Role/Permission Change (audited path)

```mermaid
flowchart LR
    A["Owner/Admin edits a user's role"] --> B["Policy check: only Owner/Admin\ncan modify roles above their own level"]
    B --> C["UserManagement Service updates role assignment"]
    C --> D["Fires PermissionsChanged event"]
    D --> E["Audit log: permission.changed\n(old role, new role, actor)"]
    D --> F["Notification: security-relevant change\nsent to Owner"]
```
