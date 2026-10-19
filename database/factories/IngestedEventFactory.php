<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\IngestedEvent;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestedEvent>
 */
class IngestedEventFactory extends Factory
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
            'event_id' => fake()->uuid(),
            'type' => EventType::PageView,
            'visitor_id' => fake()->uuid(),
            'session_id' => fake()->uuid(),
            'url' => fake()->url(),
            'occurred_at' => now(),
            'received_at' => now(),
            'status' => EventStatus::Pending,
        ];
    }
}
