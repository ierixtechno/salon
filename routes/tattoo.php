<?php

use App\Http\Controllers\Tattoo\TattooConsultationController;
use App\Http\Controllers\Tattoo\TattooProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tattoo Studio routes
|--------------------------------------------------------------------------
|
| Required from routes/web.php inside the 'tenant' middleware group.
| Same structure as routes/spa.php — see that file's header comment for
| the reasoning behind the `module:` + `can:` middleware split and the
| absence of Policy classes.
*/
Route::middleware('module:tattoo')->prefix('customers/{customer}/tattoo')->name('tattoo.')->group(function () {
    Route::middleware('can:tattoo-consultations.view')->group(function () {
        Route::get('profile/edit', [TattooProfileController::class, 'edit'])->name('profile.edit');
        Route::get('consultations', [TattooConsultationController::class, 'index'])->name('consultations.index');
    });

    Route::middleware('can:tattoo-consultations.update')
        ->put('profile', [TattooProfileController::class, 'update'])->name('profile.update');

    Route::middleware('can:tattoo-consultations.create')->group(function () {
        Route::get('consultations/create', [TattooConsultationController::class, 'create'])->name('consultations.create');
        Route::post('consultations', [TattooConsultationController::class, 'store'])->name('consultations.store');
    });
});
