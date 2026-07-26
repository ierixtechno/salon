<?php

use App\Http\Controllers\Platform\PlatformAuthController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform (Super Admin) routes
|--------------------------------------------------------------------------
|
| Entirely separate guard/session/login from tenant users (CLAUDE.md §5).
| Never mix a `web`-guard route into this file, and never mix a `platform`-
| guard route into routes/web.php.
*/

Route::prefix('platform')->name('platform.')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('login', [PlatformAuthController::class, 'create'])->name('login');
        Route::post('login', [PlatformAuthController::class, 'store']);
    });

    Route::middleware('auth:platform')->group(function () {
        Route::post('logout', [PlatformAuthController::class, 'destroy'])->name('logout');

        Route::get('dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard');

        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus'])->name('tenants.status');
        Route::patch('tenants/{tenant}/modules', [TenantController::class, 'updateModules'])->name('tenants.modules');
    });
});
