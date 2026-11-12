<?php

use App\Modules\Livestock\Controllers\LivestockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Livestock module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('livestocks')->name('api.livestocks.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [LivestockController::class, 'index'])->name('index');
    Route::post('/', [LivestockController::class, 'store'])->name('store');
    Route::get('/{livestock}', [LivestockController::class, 'show'])->name('show');
    Route::patch('/{livestock}', [LivestockController::class, 'update'])->name('update');
    Route::delete('/{livestock}', [LivestockController::class, 'destroy'])->name('destroy');
});
