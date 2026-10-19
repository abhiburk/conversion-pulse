<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventPipelineController;
use App\Http\Controllers\FailedEventRetryController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'workspace'])->group(function () {
    Route::resource('sites', SiteController::class)->except('show');
    Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('pipeline', [EventPipelineController::class, 'index'])->name('pipeline.index');
    Route::post('pipeline/failed-events/retry', [FailedEventRetryController::class, 'storeAll'])->name('failed-events.retry-all');
    Route::post('pipeline/failed-events/{ingestedEvent}/retry', [FailedEventRetryController::class, 'store'])->name('failed-events.retry');
});

require __DIR__.'/settings.php';
