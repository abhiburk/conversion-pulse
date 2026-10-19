<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'workspace_id' => fn (array $attributes) => Site::whereKey($attributes['site_id'])->value('workspace_id'),
            'name' => fake()->randomElement([
                'Exit-intent discount popup',
                'Newsletter signup bar',
                'Free shipping banner',
                'Cart abandonment popup',
                'Webinar registration form',
            ]),
            'status' => CampaignStatus::Draft,
        ];
    }

    /**
     * Indicate that the campaign is live and expected to receive traffic.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Active,
        ]);
    }
}
