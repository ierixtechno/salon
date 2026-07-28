<?php

use App\Http\Controllers\Public\PublicBookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (unauthenticated) routes
|--------------------------------------------------------------------------
|
| The app's first fully public surface (CLAUDE.md §75). Deliberately NOT
| inside the ['auth:web', 'tenant'] group in routes/web.php — there is no
| session here. Tenant context comes from ResolveTenantFromSlug, which
| resolves {tenant_slug} against the tenants table and binds it as the
| `guestTenant` container instance (see app/helpers.php) before anything
| else runs. Rate limited per IP and per tenant slug independently
| (RateLimiter::for('public-booking'), AppServiceProvider).
*/

Route::middleware(['throttle:public-booking', 'resolve-tenant'])
    ->prefix('book/{tenant_slug}')
    ->name('public.booking.')
    ->group(function () {
        Route::get('/', [PublicBookingController::class, 'show'])->name('show');
        Route::get('/slots', [PublicBookingController::class, 'slots'])->name('slots');
        Route::post('/', [PublicBookingController::class, 'store'])->name('store');
        Route::get('/confirmation/{token}', [PublicBookingController::class, 'confirmation'])->name('confirmation');
        Route::post('/confirmation/{token}/cancel', [PublicBookingController::class, 'cancel'])->name('cancel');
    });
