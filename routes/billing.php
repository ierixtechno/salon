<?php

use App\Http\Controllers\Core\TenantBillingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant billing routes (Quotations / Invoices)
|--------------------------------------------------------------------------
|
| Required from routes/web.php inside the 'tenant' middleware group.
| Quotation/PlatformInvoice are platform-owned (not BelongsToTenant), so
| TenantBillingController explicitly checks tenant_id ownership on every
| method — see that controller's class docblock.
*/
Route::middleware('can:tenant.billing.manage')->prefix('billing')->name('billing.')->group(function () {
    Route::get('plans', [TenantBillingController::class, 'plans'])->name('plans.index');
    Route::post('plans/{plan}/upgrade', [TenantBillingController::class, 'upgrade'])->name('plans.upgrade');

    Route::get('quotations', [TenantBillingController::class, 'quotations'])->name('quotations.index');
    Route::get('quotations/{quotation}', [TenantBillingController::class, 'showQuotation'])->name('quotations.show');
    Route::post('quotations/{quotation}/checkout', [TenantBillingController::class, 'createCheckout'])->name('quotations.checkout');
    Route::post('quotations/{quotation}/confirm', [TenantBillingController::class, 'confirmPayment'])->name('quotations.confirm');

    Route::get('invoices', [TenantBillingController::class, 'invoices'])->name('invoices.index');
    Route::get('invoices/{invoice}', [TenantBillingController::class, 'showInvoice'])->name('invoices.show');
});
