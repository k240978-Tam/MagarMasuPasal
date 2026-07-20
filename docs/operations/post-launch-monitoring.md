# Post-Launch Monitoring — First Watch Window

Per the Phase 8 roadmap exit criteria: a daily check-in for the first two
weeks after go-live, then weekly once things are stable. This is a manual
checklist, not automated alerting — the platform has one tenant right now,
so a human glancing at this once a day is proportionate; revisit once
there are enough tenants that manual daily review doesn't scale.

## Daily check-in (first 2 weeks)

**1. Error rate**
```bash
tail -n 200 storage/logs/laravel.log | grep -c '\.ERROR'
```
Zero is normal. A handful of `ERROR` lines from a genuinely new failure
mode (not a repeat of something already triaged) is worth reading in full —
`tail -f storage/logs/laravel.log` while a cashier is actively using the
POS is the fastest way to catch something live.

**2. Queue backlog**
```bash
php artisan queue:monitor redis:default --max=100
php artisan queue:failed
```
The queue carries journal-entry posting, notification dispatch, and the
offline-sale-outbox sync — a backlog means sales are ringing up but their
downstream side effects (accounting entries, low-stock alerts) are lagging
behind. A non-empty `queue:failed` list needs triage before it's `flush`ed;
each failed job's exception is in `failed_jobs.exception`.

**3. Scheduled jobs actually ran**
```bash
grep "backup:run\|backup:verify\|analytics:aggregate\|notifications:" storage/logs/laravel.log | tail -20
```
Confirm yesterday's backup succeeded (`backup:verify` logs a pass/fail),
the analytics aggregate ran, and the notification commands didn't error.
Scheduled tasks failing silently is the easiest kind of regression to miss
— nothing crashes the app, staff just stop getting alerts.

**4. Cash-session reconciliation accuracy**
From the CashRegister module: every closed session records
expected-vs-counted cash. In the first two weeks, a nonzero variance on
*every* session (not just an occasional till-counting slip) points at a
real bug — a payment being double-recorded, a return not deducting
correctly, etc. — not a cashier training issue.

**5. Low-stock / due-date alerts arrived**
Ask the Manager whether this morning's low-stock digest and any
customer/supplier due alerts showed up as expected (in-app notification
bell, and email if enabled). If products the shop actually ran low on
yesterday didn't trigger an alert, the per-product `min_stock` threshold
is probably set wrong, not the notification system.

## Weekly (after the first 2 weeks settle)
- Skim `storage/logs/laravel.log` for new (not previously seen) error
  signatures rather than reading every line.
- Confirm a `backup:verify` pass happened at least once in the week and
  spot-check that off-box backup mirroring (deployment.md §6) is current.
- Review `php artisan queue:failed` — clear anything triaged and resolved.
- Sanity-check the Dashboard's weekly sales trend against the Owner's own
  sense of how the week went — a large unexplained gap either way is worth
  investigating before it's dismissed as "just a slow week."

## What "stable" looks like before dropping to weekly-only
- Zero unexplained `ERROR` log entries for 3+ consecutive days.
- Queue backlog consistently near zero between check-ins, not just at the
  moment you happen to look.
- Every scheduled job's most recent run succeeded.
- Cash-session variances are occasional and small (till-counting noise),
  not systematic.
