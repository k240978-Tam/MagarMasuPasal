# 5. Screen Wireframes

Low-fidelity layout specs for the highest-priority screens. A visual mockup (static HTML,
Tailwind-styled, matching the "Square/Shopify POS quality" bar) accompanies this document —
see the Artifact shared alongside these docs, and `docs/architecture/wireframes.html` in this
repo for the source.

## 5.1 Cashier POS (primary screen, tablet/desktop, touch + keyboard)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ [Logo] Magar Masu Pasal      Branch: Halchowk   Cashier: Sita   [🔔][⚙][🌙] │
├───────────────────────────────────────────┬─────────────────────────────────┤
│  [🔍 Search / Scan barcode...........] [📷]│  CART                    [Hold] │
│                                             │ ──────────────────────────────  │
│  [All][Chicken][Mutton][Buff][Veg][Spices] │  Chicken Boneless   0.750kg     │
│  ┌───────┐┌───────┐┌───────┐┌───────┐      │  @ Rs 480/kg      Rs 360.00 [✕] │
│  │ 🐔 img││ 🐐 img││ 🐄 img││ 🥬 img│      │  ──────────────────────────────  │
│  │Chicken││Mutton ││ Buff  ││ Veg   │      │  Mutton Leg          1.2kg      │
│  │Rs480/kg││Rs1200/kg││Rs650/kg││Rs60/kg│  │  @ Rs 1200/kg   Rs 1,440.00 [✕] │
│  └───────┘└───────┘└───────┘└───────┘      │  ──────────────────────────────  │
│  ┌───────┐┌───────┐┌───────┐┌───────┐      │  Customer: [Walk-in ▾] [+Add]   │
│  │  ...  ││  ...  ││  ...  ││  ...  │      │                                  │
│  └───────┘└───────┘└───────┘└───────┘      │  Subtotal          Rs 1,800.00  │
│                                             │  Discount [  0 %]  - Rs 0.00    │
│  [⚖ Weight Entry]  [123 Qty Pad]           │  Tax               Rs 0.00      │
│                                             │  ─────────────────────────────  │
│  Held Bills: [#H1 Rs450] [#H2 Rs1200]      │  TOTAL           Rs 1,800.00    │
│                                             │                                  │
│                                             │  [💵 Cash] [📱 QR] [➗ Mixed]     │
│                                             │  [    CHARGE Rs 1,800.00    ]   │
├─────────────────────────────────────────────────────────────────────────────┤
│ F1 Search  F2 Weight  F3 Discount  F4 Customer  F5 Hold  F9 Charge  Esc Void │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Notes**
- Left pane: category-filtered product grid, large touch targets (min 64×64px), product image
  + name + price/unit; search bar supports barcode scanner input (keyboard-wedge) and manual
  typing with instant fuzzy results.
- Right pane: live cart, always visible, no page navigation needed mid-sale.
- Weight-entry and qty-pad are modal overlays triggered by tapping a weight-based product or a
  keyboard shortcut — this is generic UI, not meat-specific (a hardware store with "per-meter"
  cable uses the same weight/quantity entry component).
- Bottom bar: persistent keyboard shortcut legend (toggleable), critical for cashier speed.
- Payment buttons map directly to configured Payment Manager gateways for the tenant — a
  pharmacy with card support later just sees an extra button, no UI rebuild.

## 5.2 Customer Display (second screen, fullscreen, no input)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         [Business Logo]                                     │
│                 Magar Masu Pasal Tatha Anya Tarkari                         │
│                    Fri, 19 Jul 2026    04:12 PM     Cashier: Sita           │
├─────────────────────────────────────────────────────────────────────────────┤
│  ITEM                                QTY        PRICE          TOTAL        │
│  ───────────────────────────────────────────────────────────────────────    │
│  Chicken Boneless                  0.750 kg    Rs 480/kg     Rs 360.00      │
│  Mutton Leg                        1.200 kg   Rs 1,200/kg   Rs 1,440.00     │
│                                                                               │
│                     (large, animated add/update per item)                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  Subtotal                                                    Rs 1,800.00    │
│  Discount                                                        Rs 0.00    │
│                                                                               │
│                    ┌───────────────────────────────┐                        │
│                    │        GRAND TOTAL             │                       │
│                    │        Rs 1,800.00             │                       │
│                    └───────────────────────────────┘                        │
├─────────────────────────────────────────────────────────────────────────────┤
│           ⏳ Waiting for payment...  /  ✅ Payment Successful!               │
│                         Thank you for shopping with us!                     │
└─────────────────────────────────────────────────────────────────────────────┘
```

- Idle state (no active cart): rotates business announcements/promotions/ads full-bleed.
- State transitions (item added → payment pending → success → thank-you → idle) are
  Reverb-driven and animated (fade/slide), never a hard refresh.

## 5.3 Dashboard (role-aware landing page)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Good afternoon, Sita 👋                                    [Branch: All ▾] │
│  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐                    │
│  │ Today's   │ │ Transactions│ │ Avg Sale  │ │ Low Stock │                  │
│  │ Sales     │ │            │ │           │ │ Items     │                  │
│  │ Rs 42,300 │ │    58      │ │  Rs 729   │ │    6 ⚠    │                  │
│  └───────────┘ └───────────┘ └───────────┘ └───────────┘                    │
│  ┌─────────────────────────────┐ ┌─────────────────────────────────────┐   │
│  │ Sales Trend (7 days)  [chart]│ │ Top Selling Products      [chart]  │   │
│  └─────────────────────────────┘ └─────────────────────────────────────┘   │
│  ┌─────────────────────────────┐ ┌─────────────────────────────────────┐   │
│  │ Recent Sales               │ │ Alerts & Notifications                │   │
│  │ #INV-1042  Rs 1,800  Sita  │ │ ⚠ Chicken Boneless below min stock    │   │
│  │ #INV-1041  Rs   450  Ram   │ │ ⚠ Supplier "Kalimati Traders" due     │   │
│  └─────────────────────────────┘ └─────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
```

Widgets are permission-aware — a Cashier sees their own shift summary; an Owner sees all
branches; an Accountant sees a P&L snippet instead of stock alerts. Widget set is driven by
role/config, not hardcoded per business type.

## 5.4 Product Management (list + edit)

- List: searchable/filterable data table (category, status, stock level), bulk actions,
  CSV import/export, image thumbnail column.
- Edit form: tabbed — **General** (name, SKU, barcode, images), **Pricing** (cost/sell,
  tax rule), **Inventory** (unit, min/max stock, batch/expiry toggles), **Attributes**
  (dynamic fields rendered from `product_attribute_definitions` for the tenant's business
  type — this is where "Size/Color" vs. "Expiry/Batch" differ per tenant without code changes).

## 5.5 Inventory / Stock Management

- Stock levels table per branch (on-hand, reserved, available, min/max, status pill).
- Tabs: **Adjustments**, **Transfers**, **Batches (FIFO)**, **Movement History** (full
  filterable ledger from `stock_movements`).

## 5.6 Reports

- Left rail: report catalog grouped (Sales, Inventory, Purchases, Financial, Audit).
- Main pane: date-range + branch filter, result table, chart toggle, Export (PDF/CSV) button.

## 5.7 Responsive & Accessibility Notes

- POS is designed **tablet-first** (10"+ landscape) since that's the primary till device;
  degrades gracefully to desktop (more grid columns) and to phone (single-column, for a
  manager checking sales on the go — not for ringing up sales).
- Dark mode via Tailwind `dark:` variants across all screens, including the Customer Display
  (useful in dim shop lighting).
- Minimum touch target 44×44px (Apple HIG), color contrast AA-compliant, all interactive
  elements keyboard-reachable for the cashier keyboard-shortcut workflow.
