# Owner Guide — Magar Masu Pasal Tatha Anya Tarkari

Everything in the [Cashier](cashier-guide.md) and [Manager](manager-guide.md)
guides applies to you too. This covers what's Owner-only, and the first
things to do before the shop opens on the new system.

## Before you open
1. **Change your password** and **enable two-factor authentication**
   (Account → Two-Factor) — do this before any other staff account is
   used for real. As Owner, your account is the one an attacker gains the
   most from, and the one a mistake costs the most from.
2. **Change every other seeded staff account's password** — Manager and
   Cashier accounts start on a shared placeholder password that must not
   still be in use once real sales start.
3. Walk through [`docs/operations/go-live-checklist.md`](../operations/go-live-checklist.md)
   in full — it's written for exactly this moment.

## Business settings
Under **Settings**, you control things no other role can touch:
- **Tax rules** — what VAT/tax applies to which products.
- **Receipt template** — the shop's name, address, and contact info on
  printed receipts; the QR code section can be toggled on/off.
- **Feature toggles** — turn platform features (like stock transfers) on
  only once you actually need them, to keep the interface simple for
  staff until then.
- **Business profile** — legal name, PAN/VAT number (stored encrypted),
  currency, timezone.

## Staff & access
Add staff accounts and assign roles (Owner/Manager/Cashier/Accountant/
Inventory Manager) from **Users**. Give each person the role that matches
what they actually do — a Cashier account should not also have Manager
access "just in case." Remove access immediately when someone leaves.

## Backups
The system backs up automatically every night (database + uploaded
files), and verifies the backup afterward. You can also trigger a manual
backup any time from the **Backups** page, and see the history of past
runs there. Restoring from a backup is deliberately not a button in the
UI — it's a developer/ops action run from the server, on purpose, so it
can't happen by accident.

## API tokens
If you ever connect a mobile app or an external tool to this system,
issue it its own token from **Account → API Tokens** rather than sharing
your login. Revoke a token immediately if the device it was issued to is
lost or a contractor's engagement ends.

## Reports & the bigger picture
Beyond the day-to-day Dashboard, **Reports** and the **Accounting**
section (Chart of Accounts, Journal Entries, Trial Balance, Profit & Loss,
Balance Sheet) give you the actual financial picture — every sale,
purchase, and expense posts an accounting entry automatically, so these
reports reconcile without you doing manual bookkeeping.

## The first few weeks
Read [`docs/operations/post-launch-monitoring.md`](../operations/post-launch-monitoring.md) —
or better, ask whoever set the system up to do the daily check-in with
you for the first couple of weeks, so you know what "normal" looks like
before you're doing it alone.
