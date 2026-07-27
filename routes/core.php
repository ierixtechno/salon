<?php

use App\Http\Controllers\Core\AppointmentController;
use App\Http\Controllers\Core\AttendanceController;
use App\Http\Controllers\Core\BranchController;
use App\Http\Controllers\Core\CommissionController;
use App\Http\Controllers\Core\CustomerController;
use App\Http\Controllers\Core\CustomerMembershipController;
use App\Http\Controllers\Core\CustomerPackageController;
use App\Http\Controllers\Core\EmployeeController;
use App\Http\Controllers\Core\GiftCardController;
use App\Http\Controllers\Core\InventoryController;
use App\Http\Controllers\Core\InvoiceController;
use App\Http\Controllers\Core\LeaveRequestController;
use App\Http\Controllers\Core\LeaveTypeController;
use App\Http\Controllers\Core\LoyaltyController;
use App\Http\Controllers\Core\MembershipPlanController;
use App\Http\Controllers\Core\OrganizationSettingsController;
use App\Http\Controllers\Core\PackageController;
use App\Http\Controllers\Core\ProductCategoryController;
use App\Http\Controllers\Core\ProductController;
use App\Http\Controllers\Core\PurchaseOrderController;
use App\Http\Controllers\Core\PurchaseReturnController;
use App\Http\Controllers\Core\ResourceController;
use App\Http\Controllers\Core\ServiceCategoryController;
use App\Http\Controllers\Core\ServiceController;
use App\Http\Controllers\Core\SupplierController;
use App\Http\Controllers\Core\WaitlistEntryController;
use App\Http\Controllers\Core\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core (Phase 2) routes
|--------------------------------------------------------------------------
|
| Required from routes/web.php inside the 'tenant' middleware group, so
| everything here already has auth:web + RBAC team context + tenant status
| enforcement. Per-resource authorization is handled by Policies
| (authorizeResource in each controller), not route-level `can:` middleware,
| since these need per-instance (not just blanket) checks.
*/

Route::middleware('can:tenant.settings.manage')->group(function () {
    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])->name('settings.organization.edit');
    Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])->name('settings.organization.update');
    Route::put('/settings/organization/hours', [OrganizationSettingsController::class, 'updateHours'])->name('settings.organization.hours');
});

// No 'show' route on either — the 'edit' page is the detail view for both,
// and neither controller implements show() (Route::resource would
// otherwise register a route that 500s the moment anyone links to it).
Route::resource('branches', BranchController::class)->except(['show']);
Route::put('branches/{branch}/modules', [BranchController::class, 'updateModules'])->name('branches.modules');
Route::put('branches/{branch}/hours', [BranchController::class, 'updateHours'])->name('branches.hours');

Route::resource('branches.resources', ResourceController::class)
    ->shallow()
    ->except(['show']);

Route::resource('employees', EmployeeController::class)->except(['show']);
Route::put('employees/{employee}/schedule', [EmployeeController::class, 'updateSchedule'])->name('employees.schedule');

// destroy() deactivates (never hard-deletes, same pattern as Branch/
// Employee). Erasure is a deliberately separate, higher-stakes action —
// see EraseCustomer — never conflated with the ordinary resourceful verbs.
Route::resource('customers', CustomerController::class)->except(['show']);
Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
Route::post('customers/{customer}/consent', [CustomerController::class, 'recordConsent'])->name('customers.consent.store');
Route::post('customers/{customer}/erase', [CustomerController::class, 'erase'])->name('customers.erase');

Route::resource('service-categories', ServiceCategoryController::class)->except(['show']);

Route::resource('services', ServiceController::class)->except(['show']);
Route::put('services/{service}/variants', [ServiceController::class, 'updateVariants'])->name('services.variants');
Route::put('services/{service}/branches', [ServiceController::class, 'updateBranches'])->name('services.branches');
Route::put('services/{service}/staff', [ServiceController::class, 'updateStaff'])->name('services.staff');

// No edit/update/destroy — an appointment moves through its lifecycle via
// the dedicated actions below, it is never freeform-edited or deleted
// (CLAUDE.md §32/§48).
Route::resource('appointments', AppointmentController::class)->only(['index', 'create', 'store', 'show']);
Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->name('appointments.check-in');
Route::post('appointments/{appointment}/start', [AppointmentController::class, 'start'])->name('appointments.start');
Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('appointments.no-show');
Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
Route::put('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');

Route::resource('waitlist', WaitlistEntryController::class, ['parameters' => ['waitlist' => 'waitlist_entry']])->except(['show']);
Route::post('waitlist/{waitlist_entry}/book', [WaitlistEntryController::class, 'book'])->name('waitlist.book');

// No update/destroy on the resource route — a draft is discarded via the
// dedicated discardDraft action below, and a finalized invoice is never
// freeform-edited or deleted, only voided/refunded (CLAUDE.md §45/§48).
Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
Route::delete('invoices/{invoice}', [InvoiceController::class, 'discardDraft'])->name('invoices.discard');
Route::post('invoices/{invoice}/lines', [InvoiceController::class, 'addLine'])->name('invoices.lines.store');
Route::delete('invoices/{invoice}/lines/{invoice_line}', [InvoiceController::class, 'removeLine'])->name('invoices.lines.destroy');
Route::post('invoices/{invoice}/checkout', [InvoiceController::class, 'checkout'])->name('invoices.checkout');
Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
Route::post('invoices/{invoice}/refunds', [InvoiceController::class, 'storeRefund'])->name('invoices.refunds.store');
Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
Route::post('invoices/{invoice}/redeem-wallet', [InvoiceController::class, 'redeemWallet'])->name('invoices.redeem-wallet');
Route::post('invoices/{invoice}/redeem-loyalty', [InvoiceController::class, 'redeemLoyalty'])->name('invoices.redeem-loyalty');
Route::post('invoices/{invoice}/redeem-gift-card', [InvoiceController::class, 'redeemGiftCard'])->name('invoices.redeem-gift-card');

Route::resource('product-categories', ProductCategoryController::class)->except(['show']);
Route::resource('products', ProductController::class)->except(['show']);
Route::put('services/{service}/consumables', [ServiceController::class, 'updateConsumables'])->name('services.consumables');
Route::post('appointments/{appointment}/consumption', [AppointmentController::class, 'recordConsumption'])->name('appointments.consumption');

Route::resource('suppliers', SupplierController::class)->except(['show']);
Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'storePayment'])->name('suppliers.payments.store');

// No update/destroy — a PO moves through its lifecycle via the dedicated
// actions below (order/cancel/receive), never freeform-edited or deleted.
Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
Route::post('purchase-orders/{purchase_order}/order', [PurchaseOrderController::class, 'order'])->name('purchase-orders.order');
Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receiveGoods'])->name('purchase-orders.receive');

Route::resource('purchase-returns', PurchaseReturnController::class)->only(['index', 'create', 'store']);

Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
Route::get('inventory/adjust', [InventoryController::class, 'adjustForm'])->name('inventory.adjust.form');
Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
Route::get('inventory/transfer', [InventoryController::class, 'transferForm'])->name('inventory.transfer.form');
Route::post('inventory/transfer', [InventoryController::class, 'transfer'])->name('inventory.transfer');

// Package/Membership templates: no 'show' — 'edit' is the detail view,
// same convention as Branch/Service.
Route::resource('packages', PackageController::class)->except(['show']);
Route::put('packages/{package}/services', [PackageController::class, 'updateServices'])->name('packages.services');

Route::resource('membership-plans', MembershipPlanController::class, ['parameters' => ['membership-plans' => 'membership_plan']])->except(['show']);
Route::put('membership-plans/{membership_plan}/applicability', [MembershipPlanController::class, 'updateApplicability'])->name('membership-plans.applicability');

// Purchased instances live under a customer — sold and cancelled, never
// freeform-edited (same lifecycle-only pattern as Appointment/Invoice).
Route::get('customers/{customer}/packages', [CustomerPackageController::class, 'index'])->name('customers.packages.index');
Route::post('customers/{customer}/packages', [CustomerPackageController::class, 'store'])->name('customers.packages.store');
Route::post('customers/{customer}/packages/{customer_package}/cancel', [CustomerPackageController::class, 'cancel'])->name('customers.packages.cancel');

Route::get('customers/{customer}/memberships', [CustomerMembershipController::class, 'index'])->name('customers.memberships.index');
Route::post('customers/{customer}/memberships', [CustomerMembershipController::class, 'store'])->name('customers.memberships.store');
Route::post('customers/{customer}/memberships/{customer_membership}/cancel', [CustomerMembershipController::class, 'cancel'])->name('customers.memberships.cancel');

Route::post('appointments/{appointment}/redeem-package', [AppointmentController::class, 'redeemPackage'])->name('appointments.redeem-package');

Route::get('customers/{customer}/wallet', [WalletController::class, 'show'])->name('customers.wallet.show');
Route::post('customers/{customer}/wallet/credit', [WalletController::class, 'credit'])->name('customers.wallet.credit');

Route::get('customers/{customer}/loyalty', [LoyaltyController::class, 'show'])->name('customers.loyalty.show');

Route::get('gift-cards', [GiftCardController::class, 'index'])->name('gift-cards.index');
Route::get('gift-cards/create', [GiftCardController::class, 'create'])->name('gift-cards.create');
Route::post('gift-cards', [GiftCardController::class, 'store'])->name('gift-cards.store');
Route::post('gift-cards/{gift_card}/cancel', [GiftCardController::class, 'cancel'])->name('gift-cards.cancel');

// Phase 9: Attendance, Leave, Commission.
Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::post('attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
Route::get('my-attendance', [AttendanceController::class, 'my'])->name('attendance.my');

Route::resource('leave-types', LeaveTypeController::class)->except(['show']);

Route::get('leave', [LeaveRequestController::class, 'index'])->name('leave.index');
Route::get('my-leave', [LeaveRequestController::class, 'my'])->name('leave.my');
Route::post('leave', [LeaveRequestController::class, 'store'])->name('leave.store');
Route::post('leave/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->name('leave.approve');
Route::post('leave/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->name('leave.reject');
Route::post('leave/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave.cancel');

Route::get('commission/rules', [CommissionController::class, 'rules'])->name('commission.rules');
Route::put('commission/rules/{user}', [CommissionController::class, 'updateRule'])->name('commission.rules.update');
Route::get('commission/ledger', [CommissionController::class, 'ledger'])->name('commission.ledger');
Route::get('my-commission', [CommissionController::class, 'my'])->name('commission.my');
