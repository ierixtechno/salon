<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Tenant\OnboardingController;
use Illuminate\Support\Facades\Route;

// Registration is replaced by tenant onboarding (see docs/modules/PLATFORM.md):
// signing up creates a new tenant + owner together, not a bare user.
Route::middleware('guest')->group(function () {
    Route::get('register', [OnboardingController::class, 'create'])
        ->name('register');

    // Onboarding creates a full tenant + owner, not just a User — more
    // expensive than a login attempt and otherwise unthrottled, so an
    // automated script could spam-create tenants. Login itself already has
    // its own per-email+IP lockout baked into LoginRequest::authenticate()
    // (5 attempts), independent of route middleware — this matches that
    // same throttle:6,1 rate already used below for email verification.
    Route::post('register', [OnboardingController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('onboarding.store');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Unthrottled otherwise, a forgot-password flood is a real abuse
    // vector (spamming a victim's inbox with reset emails).
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
