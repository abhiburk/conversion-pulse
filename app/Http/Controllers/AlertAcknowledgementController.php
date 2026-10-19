<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AlertAcknowledgementController extends Controller
{
    /**
     * Record that someone is looking into an open alert. The alert stays open until the next check finds the problem has cleared.
     */
    public function store(Request $request, Alert $alert): RedirectResponse
    {
        Gate::authorize('acknowledge', $alert);

        Alert::query()
            ->whereKey($alert->id)
            ->where('status', AlertStatus::Open)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => Date::now(), 'acknowledged_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alert acknowledged.')]);

        return to_route('alerts.index');
    }
}
