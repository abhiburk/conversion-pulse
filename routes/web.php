<?php

use App\Http\Controllers\AlertAcknowledgementController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoStoreController;
use App\Http\Controllers\EventPipelineController;
use App\Http\Controllers\FailedEventRetryController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('demo', DemoStoreController::class)->name('demo');

Route::middleware(['auth', 'verified', 'workspace'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('sites', SiteController::class)->except('show');
    Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);

    Route::get('pipeline', [EventPipelineController::class, 'index'])->name('pipeline.index');
    Route::post('pipeline/failed-events/retry', [FailedEventRetryController::class, 'storeAll'])->name('failed-events.retry-all');
    Route::post('pipeline/failed-events/{ingestedEvent}/retry', [FailedEventRetryController::class, 'store'])->name('failed-events.retry');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('alerts/{alert}/acknowledgement', [AlertAcknowledgementController::class, 'store'])->name('alerts.acknowledge');
});

require __DIR__.'/settings.php';
