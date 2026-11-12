<?php

use App\Modules\Report\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Report module API routes
|--------------------------------------------------------------------------
|
| ModularServiceProvider already applies the /api prefix and the api
| middleware group, so paths here are relative to /api.
|
*/

Route::prefix('reports')->name('api.reports.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [ReportController::class, 'index'])->name('index');
});
