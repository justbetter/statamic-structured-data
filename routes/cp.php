<?php

use Illuminate\Support\Facades\Route;
use Justbetter\StatamicStructuredData\Http\Controllers\CP\ReportController;
use Justbetter\StatamicStructuredData\Http\Controllers\StructuredDataController;
use Justbetter\StatamicStructuredData\Http\Middleware\AuthorizeStructuredDataReports;

Route::prefix('justbetter')->group(function (): void {
    Route::get('structured-data', [StructuredDataController::class, 'getTemplates'])
        ->name('justbetter.structured-data.index');

    Route::middleware(AuthorizeStructuredDataReports::class)->group(function (): void {
        Route::get('structured-data/reports', [ReportController::class, 'index'])
            ->name('justbetter.structured-data.reports.index');
        Route::post('structured-data/reports', [ReportController::class, 'generate'])
            ->name('justbetter.structured-data.reports.generate');
        Route::get('structured-data/reports/{report}', [ReportController::class, 'show'])
            ->name('justbetter.structured-data.reports.show');
        Route::get('structured-data/reports/{report}/items/{item}/json-ld', [ReportController::class, 'jsonLd'])
            ->name('justbetter.structured-data.reports.json-ld');
    });
});
