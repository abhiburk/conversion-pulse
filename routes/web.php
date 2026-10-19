<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'workspace'])->group(function () {
    Route::resource('sites', SiteController::class)->except('show');
    Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

require __DIR__.'/settings.php';
