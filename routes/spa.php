<?php

use App\Http\Controllers\Spa\SpaConsultationController;
use App\Http\Controllers\Spa\SpaProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Spa (Phase 4) routes
|--------------------------------------------------------------------------
|
| Required from routes/web.php inside the 'tenant' middleware group.
| Same structure as routes/salon.php — see that file's header comment for
| the reasoning behind the `module:` + `can:` middleware split and the
| absence of Policy classes.
*/
Route::middleware('module:spa')->prefix('customers/{customer}/spa')->name('spa.')->group(function () {
    Route::middleware('can:spa-consultations.view')->group(function () {
        Route::get('profile/edit', [SpaProfileController::class, 'edit'])->name('profile.edit');
        Route::get('consultations', [SpaConsultationController::class, 'index'])->name('consultations.index');
    });

    Route::middleware('can:spa-consultations.update')
        ->put('profile', [SpaProfileController::class, 'update'])->name('profile.update');

    Route::middleware('can:spa-consultations.create')->group(function () {
        Route::get('consultations/create', [SpaConsultationController::class, 'create'])->name('consultations.create');
        Route::post('consultations', [SpaConsultationController::class, 'store'])->name('consultations.store');
    });
});
