# Deployment, Backup & Restore

Companion to [10-SHARED-HOSTING.md](10-SHARED-HOSTING.md) (design intent) — this is the actionable checklist for standing up and operating a production instance. Target: shared hosting with cPanel-style access, MySQL, cron, and no persistent worker process, per CLAUDE.md §64.

Deploying to actual cPanel hosting specifically? See [CPANEL-DEPLOYMENT-STEPS.md](CPANEL-DEPLOYMENT-STEPS.md) for the click-by-click UI steps (folder layout, Git™ Version Control, MySQL Databases, Cron Jobs, SSL) — this file stays the source of truth for the `.env` checklist, backup, and restore.

## 1. Server requirements

- PHP 8.4 (matching `composer.json`), with the extensions Laravel/this app need: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip` (used by the Phase 14 data export ZIP).
- MySQL 8.0+.
- Composer 2.
- Node/npm only at build time, to run `npm run build` (Vite) — not required on the production server itself if you build assets locally/in CI and upload `public/build`.
- Cron access (one entry — see §5).

## 2. First deployment

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Then edit `.env` — see the production checklist in §3 before going live. Once `.env` is correct:

```bash
php artisan migrate --force
php artisan db:seed --class=ModuleSeeder --force
php artisan db:seed --class=FeatureSeeder --force
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=PlatformAdminSeeder --force
npm ci && npm run build   # or upload a pre-built public/build directory
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`PlatformAdminSeeder` creates the Super Admin login from `PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD` — **change these in `.env` before running it**, and change the password immediately after first login in any environment those defaults were ever used.

### Redeploying (subsequent releases)

```bash
composer install --no-dev --optimize-autoloader
php artisan down
php artisan migrate --force
# On every deploy that changes permissions (new Permission::findOrCreate
# entries in PermissionSeeder) — additive/idempotent, safe to always run:
php artisan db:seed --class=PermissionSeeder --force
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

**A release that adds tables needs `php artisan migrate --force` — uploading files alone is not enough.** The release that introduced the error log and backups adds two tables (`error_logs`, `backup_runs`); the Super Admin sidebar degrades gracefully until they exist (no crash), but Error log and Backups pages will fail until you run the migration. Whenever you upload a new release, also clear the cached config/routes/views (`php artisan optimize:clear`, then the three `:cache` commands above) — Laravel keeps serving the old compiled copies otherwise.

Existing tenants' roles don't automatically pick up newly-added permissions from a `PermissionSeeder` update — that seeder only adds to the global permission catalog (CLAUDE.md's own docblock on that class). A tenant's Owner role only gets `syncPermissions($allPermissions)` at onboarding time. If a release adds permissions that existing tenants' Owners should have, re-sync those roles explicitly (see the `SubscriptionPlanManagementTest`-style tinker pattern used during this project's own development, or add a dedicated backfill command if this becomes routine).

## 3. Production `.env` checklist

| Variable | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | `.env.example` defaults this to `true` for local dev — **never leave true in production** (CLAUDE.md §40: never leak stack traces/SQL/paths to users). |
| `APP_URL` | your real HTTPS URL | Used for signed URLs, generated links in notifications. |
| `SESSION_SECURE_COOKIE` | `true` | Only send session/CSRF cookies over HTTPS. Left blank in `.env.example` (auto-detect) for local `http://` dev. |
| `SESSION_SAME_SITE` | `lax` (default) | Already safe; only change if you have a specific cross-site embedding need. |
| `PLATFORM_ADMIN_EMAIL` / `PLATFORM_ADMIN_PASSWORD` | your own values | Never deploy with the example defaults. |
| `QUEUE_CONNECTION` | `database` | Shared-hosting compatible — no persistent worker needed (see §5). |
| `CACHE_STORE` | `database` | Same reasoning; swappable to Redis/Memcached later without code changes. |
| `MAIL_MAILER` (+ `MAIL_HOST`/`PORT`/`USERNAME`/`PASSWORD`/`FROM_ADDRESS`) | a real transport (`smtp`, `ses`, etc.) | `.env.example` defaults to `log`, which just writes emails to the log file instead of sending them. **Billing depends on this**: a tenant who hasn't paid yet cannot log in, so the quotation, payment-received and renewal-reminder *emails* are the only way they hear from you — and it's how you get the new-signup alert, the failed-backup alert and the daily error digest. After setting it, sign up a test tenant and confirm the confirmation email actually arrives. Use a `MAIL_FROM_ADDRESS` on your own domain (a real mailbox, with SPF/DKIM set up by your host) or these will land in spam. |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` | Rotating log files instead of one that grows forever — see §6. |
| `NOTIFICATIONS_SMS_DRIVER` / `NOTIFICATIONS_WHATSAPP_DRIVER` | `null` until compliance is in place | CLAUDE.md §36 — only point these at a real provider once DLT/TRAI (SMS) and WhatsApp Business API registration are actually done. `null` logs instead of sending; safe default. |
| `FILESYSTEM_DISK` | `local` | Private uploads (expense receipts, data exports) stay off the public web root — see §7 for what this means for backups. |
| `PAYMENTS_DRIVER` | `razorpay` once you have real keys | `.env.example` defaults to `null` — checkout shows a clear "not configured yet" error instead of crashing until you set this. |
| `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` | your Razorpay checkout keys | From the Razorpay dashboard → Settings → API Keys. Use `rzp_test_...` keys first to prove the whole flow end-to-end before switching to live keys. |
| `RAZORPAY_WEBHOOK_SECRET` | your Razorpay webhook secret | See §4 below — a *separate* secret from the checkout keys above. |
| `PLATFORM_GSTIN` / `PLATFORM_STATE` / `PLATFORM_GST_RATE_PERCENT` | your GST registration | Your own GSTIN and registered state, printed on the quotations/invoices you issue to tenants. `PLATFORM_STATE` decides CGST+SGST (tenant in the same state) vs IGST (different state). Leave the GSTIN blank until you have it, but a GST invoice needs it — set it before issuing real invoices. |
| `PLATFORM_UPI_VPA` / `PLATFORM_UPI_PAYEE_NAME` | optional | Your business UPI ID — shows a scannable UPI QR on pending quotations and puts the UPI payment details in the quotation email. Hidden until set. |
| `PLATFORM_SUPPORT_EMAIL` | optional | Shown as "Questions? Write to …" at the foot of billing emails. |
| `BACKUP_RETENTION_DAYS` / `BACKUP_PATH` / `BACKUP_INCLUDE_UPLOADS` | defaults are fine | See §7. |
| `ERROR_LOG_RETENTION_DAYS` | `90` (default) | See §6. |

Also confirm the web server (Apache/Nginx) document root points at `public/`, not the project root — `.env`, `storage/`, and `app/` must never be web-accessible.

## 4. Razorpay webhook setup

The tenant billing checkout (`billing/quotations/{id}/checkout`) confirms payment two ways: the browser reports success back to the app the instant checkout completes, **and** Razorpay calls the app directly on its own server — a resilience backup so a payment is never lost even if the customer's browser closes right after paying. The second path needs a one-time setup in the Razorpay dashboard, separate from the checkout keys in §3:

1. **Razorpay dashboard → Settings → Webhooks → Add New Webhook.**
2. **Webhook URL:** `https://yourdomain.com/webhooks/razorpay`
3. **Active events:** tick `payment.captured` (nothing else is needed).
4. Razorpay shows you a **webhook secret** at this point — copy it into `.env` as `RAZORPAY_WEBHOOK_SECRET`, then `php artisan config:cache`.
5. Test-mode and live-mode each have their own webhook secret — when you eventually switch `RAZORPAY_KEY_ID`/`RAZORPAY_KEY_SECRET` from test to live, come back and add a **second** webhook entry for live mode too, and update `.env` with the live-mode secret.

Until `RAZORPAY_WEBHOOK_SECRET` is set, the endpoint safely refuses to process anything (HTTP 503) rather than silently doing nothing — the browser-side confirm alone still works fine in the meantime.

## 5. Cron & queue (no persistent worker)

Add exactly one cron entry (cPanel: Cron Jobs), running every minute:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

This drives everything already registered in `bootstrap/app.php`'s `withSchedule()`:

| When | Command | What it does |
|---|---|---|
| every minute | `queue:work --stop-when-empty --max-time=50` | Drains the `database` queue (emails, WhatsApp/SMS, tenant data exports, the "Run backup now" button). There's no persistent worker process on shared hosting, so this is how queued jobs actually run. `withoutOverlapping()`. |
| 02:00 | `backup:run` | Nightly database + uploads backup — see §7. |
| 03:00 | `data-exports:prune` | Deletes expired tenant export ZIPs. |
| 03:30 | `error-logs:prune` | Removes error-log entries not seen for `ERROR_LOG_RETENTION_DAYS` (default 90). |
| 07:30 | `error-logs:send-digest` | Emails Super Admin a summary of errors seen in the last 24 hours. Sends nothing on a quiet day. |
| 08:00 | `marketing:run-automations` | Birthday / expiry / re-engagement campaigns. |
| 08:15 | `subscriptions:process-renewals` | Renewal reminders (in-app **and** email), the auto-created renewal quotation, grace-period expiry notices, and the force-logout when a tenant's grace period ends. |

**If this one cron line isn't running, none of the above happens** — no backups, no renewal reminders, no queued email. It is the single most important thing to verify after deploying. Platform > Backups turns red when no backup has succeeded in 26 hours, which is the quickest way to notice.

If your host *does* support a long-running process (VPS/cloud), prefer a real `php artisan queue:work` under Supervisor instead of the cron-driven version above — CLAUDE.md §65 explicitly wants that swap to be config/ops-only, and it is (remove the scheduled `queue:work` entry, run a persistent worker instead).

## 6. Logs, errors & monitoring

**Three layers, each answering a different question:**

1. **Log files** (`storage/logs/`) — the raw record. Set `LOG_STACK=daily` (rotates one file per day, keeps `LOG_DAILY_DAYS`, default 14) and `LOG_LEVEL=warning` in production. The default `single` channel is one ever-growing `laravel.log` that eventually fills a shared-hosting disk, and `debug` level logs every detail. Every line carries `request_id`, plus `tenant_id`/`user_id` once known.
2. **Super Admin > Error log** — every unexpected error (500s, failed scheduled commands, failed queued jobs, failed email/SMS/WhatsApp deliveries) is recorded here, grouped so a repeating error is **one row with a counter**, not thousands. Open one to see the message, where it happened, which tenant hit it, and the call trace. Mark it resolved when fixed — it reopens by itself if it happens again. The sidebar shows a red count of open errors.
3. **Daily digest email** (07:30) — if any open error was seen in the last 24 hours, every active Super Admin gets one summary email, with new errors flagged `[NEW]`. No errors = no email, so when one arrives it's worth reading. This needs a working `MAIL_MAILER` (see §3).

**When a tenant reports "something went wrong":** every error page shows a **reference ID**. Ask for it, paste it into the Error log's search box, and you land on the exact failure — no need to ask them for screenshots or the time. The same ID is in the `X-Request-Id` response header and on every matching log line.

What the error log deliberately does **not** store: request bodies, query strings, cookies, stack-frame arguments, or the values inside database error messages — several URLs carry secrets (password-reset links, booking confirmation tokens) and database errors quote the offending row. The path is recorded as the route pattern (`/reset-password/{token}`), never the real URL. Error details are visible to Super Admin only; tenants see a friendly page with the reference ID and nothing else (CLAUDE.md §35/§40).

Also watch disk usage under `storage/app/private` (expense attachments; tenant data exports self-prune after 7 days) and `storage/backups` (§7). Tenant storage quota tracking (CLAUDE.md §34) is not implemented.

## 7. Backup

**Database and uploaded files are backed up automatically, every night at 02:00**, by `backup:run` (driven by the cron line in §5). No extra setup beyond that cron entry and a writable `storage/` folder.

- **What's in each archive** — `backup-YYYYMMDD-HHMMSS-N.zip` containing `database.sql` (every table, plain SQL), `uploads/` (expense receipts and other private files), and `manifest.json` (when, how many tables/rows, PHP/Laravel versions). Tenant data-export bundles are excluded on purpose (transient personal-data copies). Sessions, cache, queued jobs and password-reset tokens have their structure kept but **not their contents** — restoring old queued jobs would re-fire notifications, and reset tokens are credentials.
- **How it's done** — pure PHP over PDO, so it needs no `mysqldump` binary and no `exec()`, both of which shared hosts commonly disable. It reads the whole database in one consistent snapshot (a payment recorded mid-backup can't leave an invoice without its quotation), streams row by row (flat memory use on big tables), then **re-opens the finished archive and checks it** — an incomplete dump or a corrupt zip counts as a failure, not a success.
- **Where it lives** — `storage/backups/` (override with `BACKUP_PATH`): outside the web root, with an extra `.htaccess` deny. It is never served directly; the only way to fetch one is **Super Admin > Backups > Download**, which is audit-logged.
- **Retention** — archives older than `BACKUP_RETENTION_DAYS` (default 14) are deleted after a *successful* backup, and the newest 3 are always kept regardless of age, so a stretch of failed runs can never prune you down to nothing.
- **Failure handling** — a failed backup is recorded, **emailed to every active Super Admin**, and appears in the Error log. **Super Admin > Backups** shows the full history and turns red (with a red dot in the sidebar) if there's been no successful backup in 26 hours — e.g. because the cron isn't running.
- **Run one now** — Super Admin > Backups > **Run backup now** (queued; appears within a minute or two), or `php artisan backup:run`.

**These backups live on the same server as the data.** They protect against a bad update, a deleted record or a corrupted table — not against losing the server or hosting account. **Download one regularly and keep it somewhere else** (your computer, Google Drive, etc.), and/or make sure your host's own account-level backup (cPanel Backup Wizard or similar) is switched on too. Every archive contains all tenants' data, so treat downloaded copies accordingly.

**Not covered by the automatic backup — do these by hand:**

- **`.env`** — contains secrets (`APP_KEY`, DB credentials, mail/payment keys). Back it up separately from the code repository and **never** commit it to git. Losing `APP_KEY` makes encrypted data unreadable.
- Anything else you've put on disk outside `storage/app/private`.

**Suggested habit:** check Platform > Backups weekly (green = fine) and download a copy monthly. And **actually test a restore at least once** (§8) — a backup you've never restored is a hope, not a backup.

A manual `mysqldump` still works as an extra belt-and-braces layer if your host allows it:

```bash
mysqldump -u DB_USERNAME -p DB_DATABASE | gzip > backup-$(date +%Y%m%d-%H%M%S).sql.gz
```

## 8. Restore

**From an app-made backup (`backup-….zip`):**

1. Get the app running on the new/repaired server — same commit as when the backup was taken, or newer, never older (older code may not understand newer migrations already applied to the dumped database). Restore `.env` (§3 checklist plus your saved secrets).
2. Unzip the archive. Create an **empty** database (or empty the existing one) and import `database.sql`:
   ```bash
   mysql -u DB_USERNAME -p DB_DATABASE < database.sql
   ```
   or use phpMyAdmin > Import (for a large file, raise `upload_max_filesize`/`post_max_size`, or use the command line). The script drops and recreates each table, so it must go into an empty/disposable database, not one you want to keep.
3. Copy the contents of the archive's `uploads/` folder into `storage/app/private/`.
4. Run `composer install --no-dev --optimize-autoloader`, then `php artisan migrate --force` (a no-op if the dump is already current — it only applies anything genuinely new).
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Log in as Super Admin. **Sessions were deliberately not restored**, so everyone has to log in again — that's expected.
7. Re-point DNS/cron/webserver at the restored instance, confirm the §5 cron line is active, and smoke-test: Super Admin login, one tenant login, one booking flow, one invoice.

**From a manual `mysqldump` (`.sql.gz`):** `gunzip < backup-YYYYMMDD-HHMMSS.sql.gz | mysql -u DB_USERNAME -p DB_DATABASE`, then steps 3–7 above.

**Practice run:** do this once against a spare database (a scratch database in phpMyAdmin is enough) before you ever need it for real, and note how long it takes.

## 9. What's deliberately out of scope here

- **Off-site copies made automatically** — the app writes backups to its own server; copying them elsewhere is a manual download (Platform > Backups) or your host's own backup tool. Pushing to S3/Drive/etc. automatically is a sensible future addition, but needs credentials and a decision on where.
- **Encrypting backup archives** — they're plain zips; protect them as you would the database itself.
- A staging-environment setup guide — assumed to mirror production minus real payment/SMS credentials.
- Zero-downtime deployment (blue/green, rolling) — `php artisan down`/`up` (maintenance mode) is the documented approach here; a zero-downtime pipeline is a legitimate future upgrade once traffic justifies it (CLAUDE.md §65 portability applies).
