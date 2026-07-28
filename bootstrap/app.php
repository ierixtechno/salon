<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ResolveTenantFromSlug;
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
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
            'resolve-tenant' => ResolveTenantFromSlug::class,
        ]);

        // Applied to every authenticated tenant-app route via the 'tenant'
        // middleware group (see routes/web.php): resolves RBAC team context
        // and enforces tenant status, on top of standard auth:web.
        $middleware->appendToGroup('tenant', [
            SetPermissionsTeamFromTenant::class,
            EnsureTenantActive::class,
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
