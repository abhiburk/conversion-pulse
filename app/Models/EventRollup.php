<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hourly count of processed events per site, campaign and type.
 *
 * campaign_key mirrors campaign_id with 0 for events without a campaign, because unique indexes treat NULLs as distinct.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $site_id
 * @property int|null $campaign_id
 * @property int $campaign_key
 * @property EventType $type
 * @property Carbon $bucket_start
 * @property int $count
 */
class EventRollup extends Model
{
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
            'bucket_start' => 'datetime',
            'count' => 'integer',
        ];
    }

    /**
     * Get the campaign the counts belong to, if any.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
