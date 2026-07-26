<?php

use App\Http\Controllers\BeautyParlour\SkinConsultationController;
use App\Http\Controllers\BeautyParlour\SkinProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Beauty Parlour (Phase 4) routes
|--------------------------------------------------------------------------
|
| Required from routes/web.php inside the 'tenant' middleware group.
| Same structure as routes/salon.php — see that file's header comment for
| the reasoning behind the `module:` + `can:` middleware split and the
| absence of Policy classes.
*/
Route::middleware('module:beauty')->prefix('customers/{customer}/beauty')->name('beauty.')->group(function () {
    Route::middleware('can:beauty-consultations.view')->group(function () {
        Route::get('profile/edit', [SkinProfileController::class, 'edit'])->name('profile.edit');
        Route::get('consultations', [SkinConsultationController::class, 'index'])->name('consultations.index');
    });

    Route::middleware('can:beauty-consultations.update')
        ->put('profile', [SkinProfileController::class, 'update'])->name('profile.update');

    Route::middleware('can:beauty-consultations.create')->group(function () {
        Route::get('consultations/create', [SkinConsultationController::class, 'create'])->name('consultations.create');
        Route::post('consultations', [SkinConsultationController::class, 'store'])->name('consultations.store');
    });
});
