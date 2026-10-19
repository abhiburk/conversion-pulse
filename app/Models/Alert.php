<?php

namespace App\Models;

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A detected tracking problem. campaign_key mirrors campaign_id with 0 for site-level alerts so a partial unique index can allow only one open alert per subject and type.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $site_id
 * @property int|null $campaign_id
 * @property int $campaign_key
 * @property AlertType $type
 * @property AlertStatus $status
 * @property array<string, mixed> $context
 * @property Carbon $triggered_at
 * @property Carbon $last_evaluated_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $resolved_at
 */
#[Fillable(['workspace_id', 'site_id', 'campaign_id', 'campaign_key', 'type', 'status', 'context', 'triggered_at', 'last_evaluated_at', 'resolved_at'])]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'status' => AlertStatus::class,
            'context' => 'array',
            'triggered_at' => 'datetime',
            'last_evaluated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Describe what was detected, using the latest evaluation.
     */
    public function summary(): string
    {
        $context = $this->context;

        return match ($this->type) {
            AlertType::VolumeDrop => __(':metric dropped :drop%: :observed in the last :hours hours, against :expected expected from previous days.', [
                'metric' => $context['metric'] === 'impressions' ? 'Impressions' : 'Page views',
                'drop' => (int) round($context['drop'] * 100),
                'observed' => number_format($context['observed']),
                'hours' => $context['window_hours'],
                'expected' => number_format($context['expected'], 1),
            ]),
            AlertType::ProcessingFailures => trans_choice(':count event failed processing in the last :minutes minutes.|:count events failed processing in the last :minutes minutes.', $context['failed'], [
                'minutes' => $context['window_minutes'],
            ]),
            AlertType::ProcessingStalled => trans_choice(':count event has waited more than :minutes minutes to be processed. Check that the queue worker is running.|:count events have waited more than :minutes minutes to be processed. Check that the queue worker is running.', $context['pending'], [
                'minutes' => $context['stalled_after_minutes'],
            ]),
        };
    }

    /**
     * Get the workspace the alert belongs to.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the site the alert is about.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the campaign the alert is about, if any.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the user who acknowledged the alert.
     *
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
