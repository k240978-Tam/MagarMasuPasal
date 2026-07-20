# Cashier Guide — Magar Masu Pasal Tatha Anya Tarkari

## Logging in
Go to the shop's system URL, sign in with the email and password given to
you by the Owner or Manager. If you're asked for a 6-digit code after your
password, that's two-factor authentication (2FA) — enter the code from
your authenticator app, or one of your printed backup recovery codes if
you don't have your phone.

**Change your password the first time you log in**, from your account menu.

## Opening your till
Before ringing up any sale, open a cash session from the POS screen —
count the starting cash drawer and enter that amount. You can't take a
sale without an open session.

## Ringing up a sale
1. On the POS screen, add items by scanning the barcode, searching by
   name, or tapping the product tile.
2. For meat and vegetables (sold by weight), enter the weight in
   kilograms when prompted — the price is calculated automatically from
   the per-kg price.
3. The **Customer Display** (the second screen facing the customer) shows
   the cart live as you build it — the customer can follow along and
   confirm the total before paying.
4. If a customer wants to pay later or on credit, attach their name/phone
   under "Customer" — ask the Manager if you're not sure whether a
   customer is approved for credit.
5. Tap **Checkout**, choose the payment method (cash or QR — confirm QR
   payments actually arrived before completing), and confirm. The receipt
   prints automatically with a QR code the customer can scan.

## Holding a sale
If a customer steps away mid-purchase, tap **Hold** instead of abandoning
the cart. Find it again later under **Held Bills** to resume exactly where
you left off.

## Returns
For a return, find the original sale (ask the Manager if you can't locate
it) and process the return from there — never just hand back cash without
recording it, or the stock and accounting records won't match what's
actually on the shelf.

## Closing your till
At the end of your shift, close your cash session: count the cash in the
drawer and enter it. The system shows the *expected* amount (what it
calculated from your sales) next to what you counted. A small difference
is normal; if it's large, recount before finalizing and flag it to the
Manager — this variance is watched closely in the shop's first weeks on
the new system (see `docs/operations/post-launch-monitoring.md`).

## If something looks wrong
Don't try to "fix" a stuck sale by refreshing repeatedly or logging in
from two devices at once. Tell the Manager or Owner — most issues are
easier to untangle from the actual state on screen than after several
retries have layered on top of it.
