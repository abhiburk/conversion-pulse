<?php

namespace App\Jobs;

use App\Actions\ProcessEvent;
use App\Enums\EventStatus;
use App\Exceptions\EventRejected;
use App\Models\IngestedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Tries(3)]
#[Backoff([10, 60])]
class ProcessIngestedEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $ingestedEventId) {}

    /**
     * Execute the job.
     */
    public function handle(ProcessEvent $processEvent): void
    {
        try {
            $processEvent->handle($this->ingestedEventId);
        } catch (Throwable $exception) {
            $this->recordFailedAttempt($exception);

            if ($exception instanceof EventRejected) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    /**
     * Mark the event as failed once the job has given up, so it appears for inspection and manual retry.
     */
    public function failed(?Throwable $exception): void
    {
        IngestedEvent::query()
            ->whereKey($this->ingestedEventId)
            ->where('status', EventStatus::Pending)
            ->update([
                'status' => EventStatus::Failed,
                'failed_at' => Date::now(),
                'last_error' => $this->describe($exception),
            ]);
    }

    /**
     * Record the attempt and its error while the event remains pending.
     */
    private function recordFailedAttempt(Throwable $exception): void
    {
        IngestedEvent::query()
            ->whereKey($this->ingestedEventId)
            ->where('status', EventStatus::Pending)
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => $this->describe($exception),
            ]);
    }

    private function describe(?Throwable $exception): string
    {
        return Str::limit($exception?->getMessage() ?: 'Processing failed.', 1000);
    }
}
