<?php

use App\Http\Controllers\Platform\BackupController;
use App\Http\Controllers\Platform\ErrorLogController;
use App\Http\Controllers\Platform\PlatformAccountingController;
use App\Http\Controllers\Platform\PlatformAuthController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformInvoiceController;
use App\Http\Controllers\Platform\QuotationController;
use App\Http\Controllers\Platform\SubscriptionPlanController;
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
        Route::get('accounting', [PlatformAccountingController::class, 'index'])->name('accounting.index');

        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus'])->name('tenants.status');
        Route::patch('tenants/{tenant}/modules', [TenantController::class, 'updateModules'])->name('tenants.modules');
        Route::patch('tenants/{tenant}/billing-state', [TenantController::class, 'updateBillingState'])->name('tenants.billing-state');
        Route::post('tenants/{tenant}/whatsapp-credits', [TenantController::class, 'topUpWhatsappCredits'])->name('tenants.whatsapp-credits');

        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
        Route::get('subscription-plans/create', [SubscriptionPlanController::class, 'create'])->name('subscription-plans.create');
        Route::post('subscription-plans', [SubscriptionPlanController::class, 'store'])->name('subscription-plans.store');
        Route::get('subscription-plans/{subscription_plan}/edit', [SubscriptionPlanController::class, 'edit'])->name('subscription-plans.edit');
        Route::put('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'update'])->name('subscription-plans.update');

        Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::post('quotations/{quotation}/record-payment', [QuotationController::class, 'recordPayment'])->name('quotations.record-payment');
        Route::patch('quotations/{quotation}/cancel', [QuotationController::class, 'cancel'])->name('quotations.cancel');

        Route::get('invoices', [PlatformInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [PlatformInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [PlatformInvoiceController::class, 'pdf'])->name('invoices.pdf');

        // Operations: application error log and backups (Super Admin only).
        Route::get('error-logs', [ErrorLogController::class, 'index'])->name('error-logs.index');
        Route::get('error-logs/{error_log}', [ErrorLogController::class, 'show'])->name('error-logs.show');
        Route::patch('error-logs/{error_log}/resolve', [ErrorLogController::class, 'resolve'])->name('error-logs.resolve');
        Route::patch('error-logs/{error_log}/reopen', [ErrorLogController::class, 'reopen'])->name('error-logs.reopen');

        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'run'])->name('backups.run');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    });
});
