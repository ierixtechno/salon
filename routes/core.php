<?php

use App\Http\Controllers\Core\BranchController;
use App\Http\Controllers\Core\CustomerController;
use App\Http\Controllers\Core\EmployeeController;
use App\Http\Controllers\Core\OrganizationSettingsController;
use App\Http\Controllers\Core\ResourceController;
use App\Http\Controllers\Core\ServiceCategoryController;
use App\Http\Controllers\Core\ServiceController;
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
