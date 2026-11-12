<?php

use App\Modules\Health\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('health-records')->name('api.health-records.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [HealthController::class, 'index'])->name('index');
    Route::post('/', [HealthController::class, 'store'])->name('store');
    Route::get('/{healthRecord}', [HealthController::class, 'show'])->name('show');
    Route::patch('/{healthRecord}', [HealthController::class, 'update'])->name('update');
    Route::delete('/{healthRecord}', [HealthController::class, 'destroy'])->name('destroy');
});
