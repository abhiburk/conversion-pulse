<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Database\Factories\IngestedEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A raw tracking event as received from the SDK. Rows are written once by ingestion and are the durable source of truth for processing.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $site_id
 * @property int|null $campaign_id
 * @property string $event_id
 * @property EventType $type
 * @property string|null $visitor_id
 * @property string|null $session_id
 * @property string|null $url
 * @property string|null $referrer
 * @property array<string, mixed>|null $properties
 * @property Carbon $occurred_at
 * @property Carbon $received_at
 * @property EventStatus $status
 * @property Carbon|null $dispatched_at
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $processed_at
 * @property Carbon|null $failed_at
 */
class IngestedEvent extends Model
{
    /** @use HasFactory<IngestedEventFactory> */
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
            'type' => EventType::class,
            'status' => EventStatus::class,
            'properties' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Get the site the event was sent from.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the campaign the event refers to, if any.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
