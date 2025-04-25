<?php

use Illuminate\Support\Facades\Route;
use Justbetter\StatamicStructuredData\Http\Controllers\StructuredDataController;

Route::prefix('justbetter')->group(function () {
    Route::get('structured-data', [StructuredDataController::class, 'getTemplates'])->name('justbetter.structured-data.index');
});
