<?php

namespace Database\Factories;

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state: an open processing-failure alert for a site.
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
            'type' => AlertType::ProcessingFailures,
            'status' => AlertStatus::Open,
            'context' => ['failed' => 8, 'window_minutes' => 60],
            'triggered_at' => now(),
            'last_evaluated_at' => now(),
        ];
    }

    /**
     * Indicate that the alert has cleared.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
