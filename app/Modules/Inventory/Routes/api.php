<?php

use App\Modules\Inventory\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('inventories')->name('api.inventories.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::post('/', [InventoryController::class, 'store'])->name('store');
    Route::get('/{inventory}', [InventoryController::class, 'show'])->name('show');
    Route::patch('/{inventory}', [InventoryController::class, 'update'])->name('update');
    Route::delete('/{inventory}', [InventoryController::class, 'destroy'])->name('destroy');
});
