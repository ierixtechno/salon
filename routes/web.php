<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 'tenant' group = auth:web + RBAC team resolution + tenant status check
// (see bootstrap/app.php and docs/02-TENANCY.md). Every authenticated
// tenant-app screen belongs inside this group.
Route::middleware(['auth:web', 'tenant'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    require __DIR__.'/core.php';
});

require __DIR__.'/auth.php';
require __DIR__.'/platform.php';
