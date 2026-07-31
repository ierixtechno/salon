<?php

use App\Http\Controllers\Core\AccountAccessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 'tenant' group = auth:web + RBAC team resolution + tenant status check
// (see bootstrap/app.php and docs/02-TENANCY.md). Every authenticated
// tenant-app screen belongs inside this group.
Route::middleware(['auth:web', 'tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Deliberately NOT gated by can:tenant.billing.manage — any tenant
    // user must be able to see why their account is pending/blocked, not
    // just the Owner (see AccountAccessController's docblock). Exempted
    // from EnforceSubscriptionAccess's own redirect by name, alongside
    // billing.* and logout.
    Route::get('/account-access', [AccountAccessController::class, 'show'])->name('account.access');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    require __DIR__.'/core.php';
    require __DIR__.'/salon.php';
    require __DIR__.'/beauty-parlour.php';
    require __DIR__.'/spa.php';
    require __DIR__.'/tattoo.php';
    require __DIR__.'/billing.php';
});

require __DIR__.'/auth.php';
require __DIR__.'/platform.php';
require __DIR__.'/public.php';
