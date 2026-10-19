<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\IngestedEvent;
use App\Models\Site;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IngestEvents
{
    public function __construct(private DispatchEventProcessing $dispatchEventProcessing) {}

    /**
     * Validate and durably store a batch of events for a site.
     *
     * Each event is validated on its own; invalid events are reported back and skipped. Valid events are inserted in one statement that ignores rows conflicting on (site_id, event_id), so retried or concurrently duplicated events are stored exactly once.
     *
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted: int, duplicates: int, rejected: list<array{index: int, errors: array<string, array<int, string>>}>}
     */
    public function handle(Site $site, array $events): array
    {
        $receivedAt = Date::now();
        $campaignIds = $this->campaignIdsByPublicId($site, $events);

        $rows = [];
        $rejected = [];

        foreach ($events as $index => $event) {
            $validator = Validator::make($event, $this->rules($event, $campaignIds, $receivedAt));

            if ($validator->fails()) {
                $rejected[] = ['index' => $index, 'errors' => $validator->errors()->toArray()];

                continue;
            }

            $rows[] = $this->toRow($site, $validator->validated(), $campaignIds, $receivedAt);
        }

        $accepted = $rows === [] ? 0 : IngestedEvent::query()->insertOrIgnore($rows);

        $this->queueProcessing($site, $rows);

        return [
            'accepted' => $accepted,
            'duplicates' => count($rows) - $accepted,
            'rejected' => $rejected,
        ];
    }

    /**
     * Queue every event from the batch that is still pending. This includes duplicates of events whose earlier processing has not finished yet, which is harmless because processing claims each event once.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function queueProcessing(Site $site, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->dispatchEventProcessing->handle(
            IngestedEvent::query()
                ->where('site_id', $site->id)
                ->whereIn('event_id', array_column($rows, 'event_id'))
                ->where('status', EventStatus::Pending)
                ->pluck('id'),
        );
    }

    /**
     * Get the validation rules for a single event.
     *
     * @param  array<string, mixed>  $event
     * @param  Collection<string, int>  $campaignIds
     * @return array<string, mixed>
     */
    private function rules(array $event, Collection $campaignIds, CarbonInterface $receivedAt): array
    {
        $type = EventType::tryFrom(is_string($event['type'] ?? null) ? $event['type'] : '');
        $oldestAllowed = $receivedAt->subHours(config('tracking.ingest.max_event_age_hours'));

        return [
            'event_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(EventType::class)],
            'campaign_id' => [
                Rule::requiredIf($type?->requiresCampaign() ?? false),
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($campaignIds) {
                    if (! $campaignIds->has($value)) {
                        $fail(__('The campaign does not exist on this site.'));
                    }
                },
            ],
            'occurred_at' => ['required', 'date', 'after_or_equal:'.$oldestAllowed->toIso8601String()],
            'visitor_id' => ['nullable', 'string', 'max:64'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'properties' => ['nullable', 'array', 'max:'.config('tracking.ingest.max_properties')],
            'properties.*' => [
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value !== null && ! is_scalar($value)) {
                        $fail(__('Event properties must be strings, numbers, booleans or null.'));
                    }
                },
            ],
        ];
    }

    /**
     * Map the campaign public IDs referenced in the batch to the IDs of campaigns that belong to the site.
     *
     * @param  list<array<string, mixed>>  $events
     * @return Collection<string, int>
     */
    private function campaignIdsByPublicId(Site $site, array $events): Collection
    {
        $publicIds = collect($events)
            ->pluck('campaign_id')
            ->filter(fn (mixed $publicId) => is_string($publicId))
            ->unique()
            ->values();

        if ($publicIds->isEmpty()) {
            return collect();
        }

        return $site->campaigns()->whereIn('public_id', $publicIds)->pluck('id', 'public_id');
    }

    /**
     * Build the database row for a validated event. Timestamps in the future are clamped to the receive time to absorb client clock skew.
     *
     * @param  array<string, mixed>  $event
     * @param  Collection<string, int>  $campaignIds
     * @return array<string, mixed>
     */
    private function toRow(Site $site, array $event, Collection $campaignIds, CarbonInterface $receivedAt): array
    {
        $occurredAt = Date::parse($event['occurred_at'])->utc();

        return [
            'workspace_id' => $site->workspace_id,
            'site_id' => $site->id,
            'campaign_id' => isset($event['campaign_id']) ? $campaignIds->get($event['campaign_id']) : null,
            'event_id' => strtolower($event['event_id']),
            'type' => $event['type'],
            'visitor_id' => $event['visitor_id'] ?? null,
            'session_id' => $event['session_id'] ?? null,
            'url' => $event['url'] ?? null,
            'referrer' => $event['referrer'] ?? null,
            'properties' => isset($event['properties']) ? json_encode($event['properties']) : null,
            'occurred_at' => $occurredAt->greaterThan($receivedAt) ? $receivedAt : $occurredAt,
            'received_at' => $receivedAt,
            'status' => EventStatus::Pending->value,
        ];
    }
}
