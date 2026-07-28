# Deployment, Backup & Restore

Companion to [10-SHARED-HOSTING.md](10-SHARED-HOSTING.md) (design intent) — this is the actionable checklist for standing up and operating a production instance. Target: shared hosting with cPanel-style access, MySQL, cron, and no persistent worker process, per CLAUDE.md §64.

## 1. Server requirements

- PHP 8.4 (matching `composer.json`), with the extensions Laravel/this app need: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip` (used by the Phase 14 data export ZIP).
- MySQL 8.0+.
- Composer 2.
- Node/npm only at build time, to run `npm run build` (Vite) — not required on the production server itself if you build assets locally/in CI and upload `public/build`.
- Cron access (one entry — see §4).

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
| `QUEUE_CONNECTION` | `database` | Shared-hosting compatible — no persistent worker needed (see §4). |
| `CACHE_STORE` | `database` | Same reasoning; swappable to Redis/Memcached later without code changes. |
| `MAIL_MAILER` | a real transport (`smtp`, `ses`, etc.) | `.env.example` defaults to `log`, which just writes emails to the log file instead of sending them. |
| `NOTIFICATIONS_SMS_DRIVER` / `NOTIFICATIONS_WHATSAPP_DRIVER` | `null` until compliance is in place | CLAUDE.md §36 — only point these at a real provider once DLT/TRAI (SMS) and WhatsApp Business API registration are actually done. `null` logs instead of sending; safe default. |
| `FILESYSTEM_DISK` | `local` | Private uploads (expense receipts, data exports) stay off the public web root — see §6 for what this means for backups. |

Also confirm the web server (Apache/Nginx) document root points at `public/`, not the project root — `.env`, `storage/`, and `app/` must never be web-accessible.

## 4. Cron & queue (no persistent worker)

Add exactly one cron entry (cPanel: Cron Jobs), running every minute:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

This drives everything already registered in `bootstrap/app.php`'s `withSchedule()`:

- `marketing:run-automations` — daily 08:00 (birthday/expiry/re-engagement campaigns, Phase 11).
- `data-exports:prune` — daily 03:00 (deletes expired export ZIPs, Phase 14).
- `queue:work --stop-when-empty --max-time=50` — every minute, `withoutOverlapping()` (drains the `database` queue: `DeliverNotification`, `GenerateTenantDataExport`). There's no persistent worker process on shared hosting, so this is how queued jobs actually run.

If your host *does* support a long-running process (VPS/cloud), prefer a real `php artisan queue:work` under Supervisor instead of the cron-driven version above — CLAUDE.md §65 explicitly wants that swap to be config/ops-only, and it is (remove the scheduled `queue:work` entry, run a persistent worker instead).

## 5. Logs & monitoring

- `storage/logs/laravel.log` (or your configured `LOG_CHANNEL`) — every entry is tagged with `request_id`, and `tenant_id`/`user_id` once resolved (Phase 14's `AttachRequestId` / `SetPermissionsTeamFromTenant`). When a user reports an issue, ask for the `X-Request-Id` response header value (visible in browser devtools) or have them note the time — it's not surfaced in the UI by default.
- Watch disk usage under `storage/app/private` — expense attachments and data exports accumulate there (the latter self-prunes after 7 days via `data-exports:prune`; the former has no automatic cleanup yet — CLAUDE.md §34 tenant storage quota tracking is not implemented).

## 6. Backup

Three things need backing up. None of this is automated by the app itself — shared hosting typically provides its own backup tooling (cPanel "Backup Wizard" or similar); the commands below are for a manual/scripted backup or to verify what your host's tool is actually covering.

**a. Database** (everything — tenant data, platform data, sessions, cache, queue jobs):

```bash
mysqldump -u DB_USERNAME -p DB_DATABASE | gzip > backup-$(date +%Y%m%d-%H%M%S).sql.gz
```

**b. Uploaded files** — `storage/app/private` (expense attachments, in-progress/undelivered data exports) and `storage/app/public` if anything's been stored there:

```bash
tar -czf storage-backup-$(date +%Y%m%d-%H%M%S).tar.gz storage/app/private storage/app/public
```

**c. Environment config** — `.env`. Back it up separately from the code repository (it contains secrets — `APP_KEY`, DB credentials, mail/notification provider credentials) and **never** commit it to git.

Store all three somewhere other than the same server/disk (off-site or a separate storage bucket) — a backup that lives next to what it's backing up doesn't survive a disk failure. Backups must never be placed under `public/` or any web-accessible path (CLAUDE.md §67).

**Suggested cadence:** daily database dump, weekly file backup (uploads change far less often than transactional data), retained at least 30 days.

## 7. Restore

1. Provision a fresh app checkout (same commit/tag as when the backup was taken, or newer — never older, since older code may not understand newer migrations already applied to the dumped database).
2. Restore `.env` from its backup (or reconstruct it from the checklist in §3 plus your saved secrets).
3. Restore the database:
   ```bash
   gunzip < backup-YYYYMMDD-HHMMSS.sql.gz | mysql -u DB_USERNAME -p DB_DATABASE
   ```
4. Restore uploaded files:
   ```bash
   tar -xzf storage-backup-YYYYMMDD-HHMMSS.tar.gz -C /path/to/app
   ```
5. Run `composer install --no-dev --optimize-autoloader`, then `php artisan migrate --force` (safe/no-op if the dump is already fully up to date — only applies anything genuinely new).
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. Re-point DNS/cron/webserver at the restored instance, confirm `php artisan schedule:run` cron is active, and smoke-test: Super Admin login, one tenant login, one booking flow.

## 8. What's deliberately out of scope here

- Automated, scheduled backups triggered by the app itself — this is host/infrastructure responsibility, not application code, consistent with CLAUDE.md §64 (no mandatory daemon).
- A staging-environment setup guide — assumed to mirror production minus real payment/SMS credentials.
- Zero-downtime deployment (blue/green, rolling) — `php artisan down`/`up` (maintenance mode) is the documented approach here; a zero-downtime pipeline is a legitimate future upgrade once traffic justifies it (CLAUDE.md §65 portability applies).
