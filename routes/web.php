<?php

use Illuminate\Support\Facades\Route;
use KraenzleRitter\Resources\Http\Controllers\ResourcesCheckController;

/*
 * Diagnostics routes.
 *
 * Registered by ResourcesServiceProvider only when
 * config('resources.diagnostics.enabled') is true, and wrapped there in the
 * middleware from config('resources.diagnostics.middleware').
 */
Route::group(['as' => 'resources.check.'], function () {
    Route::get('/resources-check', [ResourcesCheckController::class, 'index'])->name('index');
    Route::get('/resources-check/run-all', [ResourcesCheckController::class, 'index'])->name('run-all-tests');
    Route::get('/resources-check/provider/{provider}', [ResourcesCheckController::class, 'provider'])->name('provider');
    Route::post('/resources-check/provider/{provider}/test', [ResourcesCheckController::class, 'provider'])->name('test-provider');
    Route::get('/resources-check/config', [ResourcesCheckController::class, 'config'])->name('show-config');
});
