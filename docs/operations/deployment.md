# Production Deployment Runbook

This platform runs as **one shared deployment serving every tenant** (see
[06-api-design.md §6.6](../architecture/06-api-design.md#66-versioning--deprecation-policy))
— there is no per-tenant server. This doc covers standing up that one
deployment for the first time and the release process afterward.

## 1. Server processes

Four things must run continuously, all under [Supervisor](https://supervisord.org/)
so they restart automatically on crash or reboot. Sample configs are in
[`deploy/`](../../deploy):

| Process | Config | Purpose |
|---|---|---|
| PHP-FPM + Nginx | `deploy/nginx/retail-erp.conf` | Serves the app and terminates TLS; proxies `/app` to Reverb |
| Queue worker | `deploy/supervisor/retail-erp-queue.conf` | Runs jobs (journal-entry posting, notifications, offline-sale-outbox sync) |
| Reverb | `deploy/supervisor/retail-erp-reverb.conf` | WebSocket server for POS↔Customer Display sync and live dashboard tiles |
| Cron → scheduler | see §4 below | Triggers `analytics:aggregate`, `notifications:*`, `backup:run`/`backup:verify` |

Install: copy the two `deploy/supervisor/*.conf` files to
`/etc/supervisor/conf.d/`, then `supervisorctl reread && supervisorctl update
&& supervisorctl start all`.

## 2. Database: MySQL, not SQLite

SQLite is a local-dev/CI convenience (fast, zero-setup, fine for a single
process). Production must use MySQL — concurrent POS terminals writing
sales/stock-movements from multiple branches need a real multi-writer
database, and several composite indexes added during the Phase 7 review
(`docs/architecture/06-api-design.md §6.7`) assume MySQL's query planner.

```bash
mysql -u root -p -e "CREATE DATABASE retail_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'retail_erp'@'localhost' IDENTIFIED BY '...'; GRANT ALL ON retail_erp.* TO 'retail_erp'@'localhost';"
```

Set `DB_CONNECTION=mysql` and the matching `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/
`DB_PASSWORD` in `.env` (see `deploy/env/production.env.example`), then run
migrations fresh — there is no SQLite data to migrate across engines; the
first tenant is provisioned by the seeder, not carried over from local dev.

## 3. First release

```bash
git clone <repo> /var/www/retail-erp && cd /var/www/retail-erp
git checkout main   # or whichever branch is the release branch

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp deploy/env/production.env.example .env   # then fill in real secrets
php artisan key:generate
php artisan storage:link

php artisan migrate --force
php artisan db:seed --class="Database\\Seeders\\DatabaseSeeder" --force
# Seeds platform reference data (business types, system roles, default
# units) AND provisions the first tenant, Magar Masu Pasal — see
# database/seeders/MagarMasuPasalSeeder.php. For every tenant after the
# first, provisioning is a Business/Branch/User creation through the app
# itself, not a seeder run.

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

supervisorctl reread && supervisorctl update && supervisorctl start all
```

Immediately after the first release: change every seeded staff password
(`owner@magarmasupasal.test` etc. all start with the literal password
`password` — see [go-live-checklist.md](go-live-checklist.md)) and enable
2FA on the Owner account before anyone else logs in.

## 4. Scheduler (cron)

The analytics rollup, low-stock/due-date/daily-summary notifications, and
nightly backup+verify are registered as Laravel scheduled tasks (each
module's `configureSchedules()` — see `Modules/Analytics`, `Modules/Notification`,
`Modules/Backup` `Providers/*ServiceProvider.php`), anchored to
`Asia/Kathmandu` time regardless of the server's own clock. Cron only needs
one line, running every minute:

```cron
* * * * * cd /var/www/retail-erp && php artisan schedule:run >> /dev/null 2>&1
```

Verify what's registered and when with `php artisan schedule:list`.

## 5. Subsequent releases

```bash
cd /var/www/retail-erp
php artisan down --render="errors::503" --retry=30

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize   # config:cache + route:cache + view:cache + event:cache

supervisorctl restart retail-erp-queue:* retail-erp-reverb
php artisan up
```

Zero-downtime blue/green deploys are worth revisiting once there's more
than one tenant depending on uptime during the ~10-30s `artisan down`
window — not needed for the first tenant's launch.

## 6. Backups

`backup:run` (nightly, 01:00 Asia/Kathmandu) writes a timestamped DB+media
archive; `backup:verify` (01:15) checks the most recent one is intact.
Both are Owner-triggerable manually too, from the **Backups** page in the
main nav. Restores use `php artisan backup:restore {run_id} --force` (the
`backup_runs.id` of the archive to restore, `--force` required so it can
never fire by accident) — deliberately a CLI-only, Owner-authorized
operation, not exposed over the web UI or API.

**Off-box the archives.** The `backup:run` command writes locally under
`storage/app`; production needs a cron/cloud-sync step (e.g. `rclone` or
`aws s3 sync`) copying that directory somewhere that survives the app
server itself dying — not covered by the application code, since where
backups are mirrored to is an infrastructure choice, not a business rule.

## 7. Zero real payment gateways yet

Only `cash` and `manual_qr` (staff-confirmed) payment methods exist — see
[06-api-design.md §6.3](../architecture/06-api-design.md#63-payment-manager-interfaces).
There is nothing to configure for eSewa/Khalti/FonePay because nothing
implements them yet; this is intentional, not a deployment gap.
