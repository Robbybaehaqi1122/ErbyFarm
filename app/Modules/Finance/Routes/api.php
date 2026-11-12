<?php

use App\Modules\Finance\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('expenses')->name('api.expenses.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [FinanceController::class, 'index'])->name('index');
    Route::post('/', [FinanceController::class, 'store'])->name('store');
    Route::get('/{expense}', [FinanceController::class, 'show'])->name('show');
    Route::patch('/{expense}', [FinanceController::class, 'update'])->name('update');
    Route::delete('/{expense}', [FinanceController::class, 'destroy'])->name('destroy');
});
