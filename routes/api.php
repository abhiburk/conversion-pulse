<?php

use App\Http\Controllers\Api\IngestEventsController;
use Illuminate\Support\Facades\Route;

Route::post('v1/events', IngestEventsController::class)
    ->middleware('throttle:ingest')
    ->name('api.events.store');
