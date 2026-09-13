# Deploying to cPanel Shared Hosting — Step by Step

Companion to [DEPLOYMENT.md](DEPLOYMENT.md) (the authoritative checklist: `.env` production values, cron/queue, backup, restore) and [10-SHARED-HOSTING.md](10-SHARED-HOSTING.md) (design intent). This file covers only the parts that are specific to cPanel's UI and folder layout — the *mechanics* of getting this Laravel app running on a typical cPanel account. Read DEPLOYMENT.md §3 (the `.env` table) alongside this; it isn't repeated here.

## 0. Before you start — confirm what your plan gives you

Open **cPanel → Advanced → Terminal**. If it's there and opens a shell, you have SSH-equivalent access and can run `composer`/`php artisan` commands directly — most of this guide assumes that. If Terminal isn't offered on your plan, see §7 (No-Terminal fallback) — Laravel genuinely needs `php artisan` to run migrations and caches, so you'll need to run those from your own machine and upload the results, or ask your host to enable it.

Also check **cPanel → Software → Select PHP Version** (or "MultiPHP Manager") for what PHP versions are installed — you need **PHP 8.3 or newer** (`composer.json` requires `^8.3`; this app was built against 8.4).

## 1. The Laravel-on-cPanel folder problem

cPanel serves whatever is in `public_html/` (or a domain's configured document root) directly to the web. Laravel's own `public/` folder — the *only* folder that should be web-accessible — sits one level inside the project, next to `app/`, `.env`, `storage/`, etc. If you just unzip the whole app straight into `public_html/`, your `.env` file (database password, `APP_KEY`) becomes downloadable by anyone who requests `https://yourdomain.com/.env`. **Do not do that.**

Two correct approaches — pick based on what your host allows:

**Option A — dedicated domain/subdomain with a custom document root (preferred, if your host allows it):**
1. Upload/clone the whole app to a folder *outside* `public_html`, e.g. `/home/yourcpaneluser/salon` (via Terminal or File Manager, one level up from the web root).
2. In **cPanel → Domains**, edit the domain/subdomain you're deploying to and set its **Document Root** to `/home/yourcpaneluser/salon/public`.
3. Done — cPanel now serves `public/index.php` directly, and everything else in `salon/` (including `.env`) is outside the web root and unreachable by URL.

**Option B — your host only lets you point a domain at `public_html` (no custom document root option):**
1. Upload/clone the app to `/home/yourcpaneluser/salon` (outside `public_html`).
2. Move the *contents* of `salon/public/` into `public_html/` (not the `public` folder itself — its contents: `index.php`, `.htaccess`, `build/`, etc.).
3. Edit the two paths at the top of the `index.php` you just moved into `public_html/` so they still point at the real app folder:
   ```php
   require __DIR__.'/../salon/vendor/autoload.php';
   $app = require_once __DIR__.'/../salon/bootstrap/app.php';
   ```
   (adjust `../salon` to wherever you actually put the app folder relative to `public_html`)
4. This is more fragile on redeploys (you must remember to re-copy `public/` contents and re-patch `index.php` after every `git pull`) — prefer Option A if at all possible; ask your host's support whether a custom document root is available before settling for this.

The rest of this guide assumes **Option A**.

## 2. Get the code onto the server

You already have this repo on GitHub at `https://github.com/ierixtechno/salon`. Two ways to pull it into cPanel:

**Via cPanel's Git™ Version Control app** (Manager → Git™ Version Control, if your host offers it):
1. "Create" → repository path `/home/yourcpaneluser/salon`, clone URL `https://github.com/ierixtechno/salon.git`, branch `main`.
2. If the repo were private you'd need a deploy key/PAT here — it's public, so a plain HTTPS clone works with no credentials.

**Via Terminal (if you have it — simplest):**
```bash
cd ~
git clone https://github.com/ierixtechno/salon.git salon
cd salon
```

Either way, you should now have `/home/yourcpaneluser/salon` containing this repo, with `public/` as a subfolder — proceed to Option A's step 2 (set the domain's document root to `.../salon/public`).

## 3. PHP version & extensions

**cPanel → Software → MultiPHP Manager**: select your domain, set it to PHP 8.3 or 8.4.

**cPanel → Software → Select PHP Version** (or "MultiPHP INI Editor") → confirm these extensions are enabled: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`. Shared hosts almost always have these on by default; `zip` and `bcmath` are the two most commonly missing and worth double-checking (used by the data-export ZIP feature and by money rounding).

## 4. Database

**cPanel → Databases → MySQL® Databases**:
1. Create a database (e.g. `yourcpaneluser_salon`) — cPanel prefixes it with your account name automatically.
2. Create a database user with a strong password, "Add User to Database" with **All Privileges**.
3. Note the three values: database name, username, password. Host is almost always `localhost` on cPanel.

## 5. Composer

If Terminal is available:
```bash
cd ~/salon
composer install --no-dev --optimize-autoloader
```
If `composer` isn't on the `PATH`, cPanel usually ships it as `/opt/cpanel/composer/bin/composer` or via a "Composer Manager" app in cPanel — check there before assuming it's missing entirely.

If Composer genuinely isn't available on your plan: run `composer install --no-dev --optimize-autoloader` **on your own machine**, then upload the resulting `vendor/` folder (it's large — zip it first, upload the zip via File Manager, then extract in place; uploading thousands of individual files over FTP is painfully slow).

## 6. `.env`

```bash
cd ~/salon
cp .env.example .env
php artisan key:generate
```
Then edit `.env` (File Manager's editor, or `nano .env` in Terminal) with:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from §4, `DB_HOST=localhost`.
- Everything in **DEPLOYMENT.md §3** — `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain.com`, `SESSION_SECURE_COOKIE=true`, your real `PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, a real `MAIL_MAILER`.
- Leave `PAYMENTS_DRIVER=null`, `NOTIFICATIONS_SMS_DRIVER=null`, `NOTIFICATIONS_WHATSAPP_DRIVER=null` until you actually have Razorpay/SMS/WhatsApp credentials — see `.env.example`'s comments.
- `PLATFORM_STATE` is already defaulted to `Haryana`; set `PLATFORM_GSTIN` once you have a real GST registration number.

## 7. Migrate, seed, build, cache

```bash
cd ~/salon
php artisan migrate --force
php artisan db:seed --class=ModuleSeeder --force
php artisan db:seed --class=FeatureSeeder --force
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=PlatformAdminSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

For front-end assets: if Node/npm isn't available on the server (common on shared hosting), run `npm ci && npm run build` **on your own machine** first, commit or otherwise get the generated `public/build/` folder onto the server (it's gitignored by default — upload it directly via File Manager/zip, or temporarily remove it from `.gitignore` for this repo if you'd rather deploy it through git). If your host does have Node in Terminal, just run `npm ci && npm run build` there instead.

### No-Terminal fallback

If your plan truly has no Terminal/SSH and no Composer app: do steps 5–7 entirely on your own machine against a local copy pointed at throwaway settings, then upload the whole resulting folder (`vendor/`, `public/build/`, everything) via File Manager/FTP — except `.env`, which you create fresh directly on the server with production values, and except `storage/` and `bootstrap/cache/`, which need to exist and be writable on the server itself, not copied from local (see §8). You will not be able to run `php artisan migrate` remotely without *some* form of shell access — ask your host whether a one-off SSH session can be enabled, even temporarily, or whether they can run the migration for you. This is the one genuinely hard blocker on a fully click-only host.

## 8. File permissions

`storage/` and `bootstrap/cache/` must be writable by the web server process. On cPanel, PHP typically runs as your own cPanel user (via suPHP/FastCGI), so this is usually already correct once you own the files — but if you hit "permission denied" errors in `storage/logs/laravel.log`:
```bash
chmod -R 775 storage bootstrap/cache
```

## 9. Cron (queue + scheduled jobs)

**cPanel → Advanced → Cron Jobs** → add one entry, every minute:
```
* * * * * cd /home/yourcpaneluser/salon && php artisan schedule:run >> /dev/null 2>&1
```
If plain `php` on the cron line doesn't resolve to PHP 8.3+, use the full path shown in MultiPHP Manager for your chosen version, e.g. `/usr/local/bin/ea-php83` instead of `php`. This one cron line drives everything in DEPLOYMENT.md §5 — the marketing automations, export pruning, and the database-queue worker (there's no persistent worker process on shared hosting, so this is how `DeliverNotification`/`GenerateTenantDataExport` jobs actually run).

## 10. HTTPS

**cPanel → Security → SSL/TLS Status** (or "Let's Encrypt™ SSL" if your host uses that instead of AutoSSL) → issue a certificate for your domain. Once it's active, confirm `APP_URL` in `.env` uses `https://` and `SESSION_SECURE_COOKIE=true`, then re-run `php artisan config:cache`.

## 11. Go-live smoke test

1. Visit `https://yourdomain.com/platform/login` — log in with the `PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD` you set in `.env`, then **change that password immediately**.
2. Create a test tenant (or visit `/register` and sign up as one) — confirm the pending-payment flow and Super Admin quotation/invoice screens render correctly, including the GST breakdown.
3. Confirm the cron is actually firing: check `storage/logs/laravel.log` a few minutes after setup, or watch `platform_audit_logs`/`notification_logs` after triggering something that queues a notification.
4. Confirm `https://yourdomain.com/.env` returns a 404, not your database password — this is the single most important check if you used Option B in §1.

## 12. Redeploying later

```bash
cd ~/salon
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan down
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force
npm ci && npm run build   # or re-upload public/build if Node isn't on the server
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```
(Same sequence as DEPLOYMENT.md's "Redeploying" section — repeated here since it's the one you'll actually run from this folder.)

## What this guide doesn't cover

Backup/restore procedure, the full `.env` production checklist, and what to do if you have a VPS instead of shared hosting — see [DEPLOYMENT.md](DEPLOYMENT.md) for all of that.
