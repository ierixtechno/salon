<?php

use App\Domain\Platform\Support\RecordErrorLog;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnforceSubscriptionAccess;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ResolveTenantFromSlug;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPermissionsTeamFromTenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // CLAUDE.md §44: idempotent per tenant per day — a missed or
        // doubled cron firing never duplicates a marketing send (see
        // RunsCampaignAutomation). Shared hosting runs this via the
        // standard `php artisan schedule:run` cron entry (CLAUDE.md §64).
        $schedule->command('marketing:run-automations')->dailyAt('08:00');

        // CLAUDE.md §44: exact-day matching (see the command's docblock)
        // keeps this idempotent per tenant per day.
        $schedule->command('subscriptions:process-renewals')->dailyAt('08:15');

        // CLAUDE.md §34/§36: don't let exported PII bundles sit on disk
        // past their stated 7-day availability window.
        $schedule->command('data-exports:prune')->dailyAt('03:00');

        // CLAUDE.md §67: daily database + uploads backup. Runs before the
        // other 02:xx-03:xx housekeeping so it captures the day's data
        // before anything is pruned; withoutOverlapping() guards a slow
        // run (a large database) against the next day's firing. A failed
        // run emails Super Admin and shows red on Platform > Backups.
        $schedule->command('backup:run')->dailyAt('02:00')->withoutOverlapping(180);

        // Heartbeat: proves the server's cron is really firing. Shown as a
        // red banner on the Platform pages when it goes quiet, and (if
        // HEALTHCHECK_PING_URL is set) pinged to an external monitor that
        // emails when the pings stop — the app can't report its own death.
        $schedule->call(fn () => \Illuminate\Support\Facades\Cache::put(\App\Domain\Platform\Support\PlatformHealth::HEARTBEAT_KEY, now()->getTimestamp(), now()->addDay()))
            ->name('scheduler-heartbeat')
            ->everyMinute();

        if ($pingUrl = config('platform.health.ping_url')) {
            $schedule->call(fn () => null)->name('external-heartbeat-ping')->everyFiveMinutes()->pingOnSuccess($pingUrl);
        }

        // Emails Super Admin when backups, disk, the queue or failed jobs
        // need attention — each problem at most once a day.
        $schedule->command('health:check')->hourly();

        // Error-log housekeeping + the once-a-day summary email to Super
        // Admin (skipped entirely on a day with nothing to report).
        $schedule->command('error-logs:prune')->dailyAt('03:30');
        $schedule->command('error-logs:send-digest')->dailyAt('07:30');

        // Shared hosting has no persistent `queue:work` process (CLAUDE.md
        // §43/§64) — this drains the database queue (DeliverNotification,
        // GenerateTenantDataExport) once a minute instead. --stop-when-empty
        // exits promptly rather than looping forever; --max-time bounds it
        // safely under the 1-minute cron interval; withoutOverlapping()
        // guards against a slow run still executing when the next fires.
        $schedule->command('queue:work --stop-when-empty --max-time=50')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
            'resolve-tenant' => ResolveTenantFromSlug::class,
        ]);

        // Provider webhooks carry no CSRF token (the caller is Razorpay's
        // server, not a browser with our session) — authenticity comes
        // from the provider's own signature check instead (CLAUDE.md §31),
        // done inside RazorpayWebhookController itself.
        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay',
        ]);

        // Every response, every route, including the public booking
        // surface — response header hardening (CLAUDE.md §14) and the
        // request-correlation id (CLAUDE.md §41) apply universally, not
        // just to the authenticated tenant app.
        $middleware->append([
            AttachRequestId::class,
            SecurityHeaders::class,
        ]);

        // Applied to every authenticated tenant-app route via the 'tenant'
        // middleware group (see routes/web.php): resolves RBAC team context
        // and enforces tenant status, on top of standard auth:web.
        $middleware->appendToGroup('tenant', [
            SetPermissionsTeamFromTenant::class,
            EnsureTenantActive::class,
            EnforceSubscriptionAccess::class,
        ]);

        // An unauthenticated hit on a platform:: route must bounce to the
        // Super Admin login, never the tenant login — the two guards are
        // deliberately separate worlds (CLAUDE.md §5).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform/*')
            ? route('platform.login')
            : route('login'));

        // The mirror image: an already-authenticated user hitting a
        // `guest`-only route (e.g. a platform admin revisiting
        // /platform/login) must land on *their own* dashboard. Laravel's
        // default RedirectIfAuthenticated isn't guard-aware, so without
        // this it sends a platform admin to the tenant `dashboard` route,
        // which then bounces to the tenant /login since there's no `web`
        // session — a confusing dead end.
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('platform/*')
            ? route('platform.dashboard')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every reported exception also lands in the Platform error log
        // (Super Admin > Error log, plus the daily digest email). Returns
        // nothing, so Laravel's normal reporting to the log file still
        // happens too — this adds to it, never replaces it.
        $exceptions->report(function (Throwable $e): void {
            app(RecordErrorLog::class)->record($e);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
