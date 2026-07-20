# Manager Guide — Magar Masu Pasal Tatha Anya Tarkari

Everything in the [Cashier Guide](cashier-guide.md) applies to you too —
you can run the POS the same way. This covers what's additionally yours.

## Inventory
- **Receiving stock:** when a supplier delivery arrives, record it as a
  Purchase Order → Receive Goods, entering the actual quantity and cost
  per item, plus batch number and expiry date for meat/vegetables. This is
  what keeps stock-on-hand accurate — skipping it means the POS will let
  cashiers sell items that aren't really there.
- **Low-stock alerts:** you'll get a notification each morning (~06:45,
  before opening) listing anything at or below its reorder threshold.
  Use it to plan the day's purchasing, not just as an FYI.
- **Adjustments:** if stock is wrong because of spoilage, wastage, or a
  miscount, record a Stock Adjustment with a reason — don't silently edit
  quantities, since every change is tracked in the audit log and feeds the
  accounting entries.
- **Stock transfers:** once there's more than one branch, use Stock
  Transfer (not a manual adjustment on each side) to move stock between
  them — it keeps both branches' records and the shared audit trail
  consistent.

## Customers & credit
Hotel/restaurant customers who buy on account are set up as "Credit"
customers with a credit limit. You'll get a due-date alert (~07:00 daily)
for anyone near or over their limit, and for suppliers the shop owes above
a configured threshold — follow up on both promptly; the credit limit
exists so a subscription's failure to pay doesn't silently become the
shop's problem to absorb.

## Reports & the Dashboard
The Dashboard (front page after login) shows today's sales, a trend
chart, and low-stock items at a glance. **Reports** (top nav) has deeper
detail: sales by date range/branch, top-selling products, peak selling
hours, and inventory reports — each exportable as PDF/CSV if you need to
share it or keep an offline copy.

## Cash sessions
You can review any cashier's closed session (expected vs. counted cash,
and the variance) from the Cash Register section — spot-check these
regularly, especially in the shop's first weeks on the system.

## Things only the Owner can do
Business settings (tax rules, receipt template, feature toggles), staff
accounts and roles, API tokens, and backups are Owner-only. If you need
one of those changed, ask the Owner directly rather than trying to work
around it.
