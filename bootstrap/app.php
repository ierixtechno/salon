<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\SetPermissionsTeamFromTenant;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
