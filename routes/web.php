<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
    Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');

    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/members', [ImportController::class, 'members'])->name('imports.members');
    Route::post('/imports/loans', [ImportController::class, 'loans'])->name('imports.loans');
    Route::delete('/imports/{batch}', [ImportController::class, 'destroy'])->name('imports.destroy');

    Route::get('/exports/registry', [ExportController::class, 'registry'])->name('exports.registry');
    Route::get('/exports/gad', [ExportController::class, 'gad'])->name('exports.gad');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
