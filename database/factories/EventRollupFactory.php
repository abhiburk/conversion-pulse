<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRollup>
 */
class EventRollupFactory extends Factory
{
    /**
     * Define the model's default state: site page views in the current hour.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'workspace_id' => fn (array $attributes) => Site::whereKey($attributes['site_id'])->value('workspace_id'),
            'campaign_id' => null,
            'campaign_key' => 0,
            'type' => EventType::PageView,
            'bucket_start' => now()->startOfHour(),
            'count' => 1,
        ];
    }

    /**
     * Count events of the given type for a campaign.
     */
    public function forCampaign(Campaign $campaign, EventType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => $campaign->workspace_id,
            'site_id' => $campaign->site_id,
            'campaign_id' => $campaign->id,
            'campaign_key' => $campaign->id,
            'type' => $type,
        ]);
    }
}
