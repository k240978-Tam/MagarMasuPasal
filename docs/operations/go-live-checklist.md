# Go-Live Checklist — Magar Masu Pasal Tatha Anya Tarkari

Run through this once, on the real production deployment, before the
Halchowk branch takes its first real-money sale. Each item names the
concrete thing to check, not just a restated goal.

## Infrastructure
- [ ] MySQL (not SQLite) is the active `DB_CONNECTION`; `php artisan migrate --force` ran clean.
- [ ] `APP_ENV=production`, `APP_DEBUG=false` — a stack trace must never reach a customer's browser.
- [ ] HTTPS is enforced (Nginx redirects :80 → :443); `SESSION_SECURE_COOKIE=true`.
- [ ] Supervisor is running and set to autostart on reboot for: PHP-FPM, the queue worker, Reverb.
- [ ] `* * * * * php artisan schedule:run` is in crontab; `php artisan schedule:list` shows all 6 jobs (`backup:run`, `backup:verify`, `analytics:aggregate`, `notifications:daily-summary`, `notifications:low-stock`, `notifications:dues`) anchored to `Asia/Kathmandu`.
- [ ] `php artisan backup:run` succeeds manually once, and `backup:verify` confirms it — before trusting the nightly schedule.
- [ ] Backup archives are mirrored off the app server (see deployment.md §6) — a backup that lives only on the machine it protects isn't a backup.

## Tenant data
- [ ] Business "Magar Masu Pasal Tatha Anya Tarkari" exists with `business_type = meat_shop`, currency `NPR`, timezone `Asia/Kathmandu`.
- [ ] Halchowk branch exists and is marked `is_main`; at least one POS terminal is registered under it.
- [ ] The real product catalog (25 items across Chicken/Mutton/Buff/Pork/Eggs/Vegetables — see `database/seeders/MagarMasuPasalSeeder.php`) matches what the shop actually stocks and prices *today* — seeded prices are a starting point, not guaranteed current.
- [ ] Opening stock quantities were counted by staff, not left as the seeder's placeholder numbers.
- [ ] Chart of accounts is seeded and opening capital / opening balances are correct (the seeder posts a placeholder ₨100,000 opening-capital entry — replace with the real figure).

## Accounts & security
- [ ] Every seeded staff account's password has been changed from the literal seed value `password` (`owner@…`, `manager@…`, `cashier@…`).
- [ ] The Owner account has 2FA enabled (`/account/two-factor`) before any other login happens.
- [ ] Role assignments match reality: exactly the right people have Owner/Manager/Cashier, and no test/demo accounts remain active.
- [ ] If a mobile client or third-party integration needs one, an API token has been issued from `/account/api-tokens` and the plaintext value stored somewhere safe (it's shown once).

## Business configuration
- [ ] Feature toggles in Settings reflect what this tenant actually uses (e.g. stock transfers only matters once there's a second branch).
- [ ] The receipt template is customized with the shop's real name/address/contact — not the placeholder defaults.
- [ ] Tax rules match what the shop is legally required to charge (VAT/PAN number set on the business profile — this field is encrypted at rest, see the Phase 6 security review).
- [ ] Low-stock thresholds (`min_stock` per product, or per-branch overrides) are set to numbers that give staff enough lead time to reorder — the seeded value of 5 for every product is a placeholder.

## Dry run
- [ ] A full sale (barcode/search → cart → weight entry for at least one weight-based item → payment → receipt) has been run on the real terminal hardware that will be used, not just in a browser.
- [ ] The Customer Display mirrors the cart live, with no polling delay, on the real second screen.
- [ ] A cash session was opened, a sale rung up, and the session closed with a correct reconciliation (counted cash matches expected).
- [ ] A hold/resume and a return were each exercised once end-to-end.
- [ ] Staff have physically practiced login, 2FA (if assigned), and the full sale flow — not just watched a demo.

## Sign-off
- [ ] Security review sign-off from Phase 6 (`docs/architecture/08-roadmap.md` Phase 6 exit criteria) is still valid — no un-reviewed changes have landed on `main` since.
- [ ] This checklist itself has been run by someone who did **not** build the system, to catch "obvious once you use it" gaps a developer wouldn't notice.

Once every box above is checked, proceed to
[post-launch-monitoring.md](post-launch-monitoring.md) for the first-week watch.
