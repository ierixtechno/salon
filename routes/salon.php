<?php

use App\Http\Controllers\Salon\HairConsultationController;
use App\Http\Controllers\Salon\HairProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Salon (Phase 4) routes
|--------------------------------------------------------------------------
|
| Required from routes/web.php inside the 'tenant' middleware group.
| `module:salon` enforces tenant-level module access up front (CLAUDE.md
| §9); branch-level module enforcement happens inside the FormRequest
| (StoreHairConsultationRequest) since these routes carry no {branch} route
| parameter for the `module` middleware to inspect.
|
| No Policy classes here (same precedent as OrganizationSettingsController):
| permission is enforced declaratively via `can:` route middleware plus a
| matching authorize() check in each FormRequest, and tenant isolation comes
| from Customer's own tenant-scoped route model binding.
*/
Route::middleware('module:salon')->prefix('customers/{customer}/salon')->name('salon.')->group(function () {
    Route::middleware('can:salon-consultations.view')->group(function () {
        Route::get('profile/edit', [HairProfileController::class, 'edit'])->name('profile.edit');
        Route::get('consultations', [HairConsultationController::class, 'index'])->name('consultations.index');
    });

    Route::middleware('can:salon-consultations.update')
        ->put('profile', [HairProfileController::class, 'update'])->name('profile.update');

    Route::middleware('can:salon-consultations.create')->group(function () {
        Route::get('consultations/create', [HairConsultationController::class, 'create'])->name('consultations.create');
        Route::post('consultations', [HairConsultationController::class, 'store'])->name('consultations.store');
    });
});
