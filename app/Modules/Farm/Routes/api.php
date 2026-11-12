<?php

use App\Modules\Farm\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Farm module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('farms')->name('api.farms.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [FarmController::class, 'index'])->name('index');
    Route::post('/', [FarmController::class, 'store'])->name('store');
    Route::get('/{farm}', [FarmController::class, 'show'])->name('show');
    Route::patch('/{farm}', [FarmController::class, 'update'])->name('update');
    Route::delete('/{farm}', [FarmController::class, 'destroy'])->name('destroy');
});
