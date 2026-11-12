<?php

use App\Modules\Production\Controllers\DailyProductionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Production module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('daily-productions')->name('api.daily-productions.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [DailyProductionController::class, 'index'])->name('index');
    Route::post('/', [DailyProductionController::class, 'store'])->name('store');
    Route::get('/{dailyProduction}', [DailyProductionController::class, 'show'])->name('show');
    Route::patch('/{dailyProduction}', [DailyProductionController::class, 'update'])->name('update');
    Route::delete('/{dailyProduction}', [DailyProductionController::class, 'destroy'])->name('destroy');
});
