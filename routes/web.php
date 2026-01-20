<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InvoiceController;

Route::get('/', function () {
    return view('welcome');
});

// Family routes
Route::middleware(['auth'])->group(function () {
    Route::get('/family', [FamilyController::class, 'dashboard'])->name('family.dashboard');
    Route::get('/family/create', [FamilyController::class, 'create'])->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store');
    Route::get('/family/{id}', [FamilyController::class, 'show'])->name('family.show');
    Route::get('/family/{id}/edit', [FamilyController::class, 'edit'])->name('family.edit');
    Route::put('/family/{id}', [FamilyController::class, 'update'])->name('family.update');
    Route::delete('/family/{id}', [FamilyController::class, 'destroy'])->name('family.destroy');

    // Invoice routes
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{id}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::get('/invoices/pay-all', [InvoiceController::class, 'payAll'])->name('invoices.pay-all');
});
