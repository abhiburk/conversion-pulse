<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * List open alerts and recently resolved ones for the current workspace.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Alert::class);

        $alerts = $request->user()->currentWorkspace->alerts()
            ->with(['site:id,name,domain', 'campaign:id,name', 'acknowledgedBy:id,name']);

        return Inertia::render('alerts/Index', [
            'openAlerts' => (clone $alerts)
                ->where('status', AlertStatus::Open)
                ->orderByDesc('triggered_at')
                ->orderByDesc('id')
                ->get()
                ->map($this->present(...)),
            'resolvedAlerts' => (clone $alerts)
                ->where('status', AlertStatus::Resolved)
                ->orderByDesc('resolved_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->through($this->present(...)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'label' => $alert->type->label(),
            'summary' => $alert->summary(),
            'site' => $alert->site->only(['id', 'name', 'domain']),
            'campaign' => $alert->campaign?->only(['id', 'name']),
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'last_evaluated_at' => $alert->last_evaluated_at->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $alert->acknowledgedBy?->name,
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ];
    }
}
