<?php

namespace App\Jobs;

use App\Models\WeeklyMealDraft;
use App\Services\Meals\WeeklyMealPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GenerateWeeklyMealDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;
    public bool $failOnTimeout = true;

    public function __construct(public int $draftId)
    {
        $this->onConnection('database');
        $this->onQueue('meal-plans');
    }

    public function handle(WeeklyMealPlanner $planner): void
    {
        // A redelivered job cannot start a second paid generation.
        $claimed = WeeklyMealDraft::query()->whereKey($this->draftId)
            ->where('status', 'queued')
            ->update(['status' => 'generating', 'updated_at' => now()]);

        if (! $claimed) {
            return;
        }

        $draft = WeeklyMealDraft::query()->findOrFail($this->draftId);
        $lock = Cache::lock('meal-ideas:user:'.$draft->user_id, 600);
        $acquired = false;

        try {
            $acquired = $lock->get();

            if (! $acquired) {
                throw new RuntimeException('Another generation is in progress.');
            }

            set_time_limit(300);
            $planner->generate($draft);
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->first()
            : 'Weekly generation stopped. Your existing plan was not changed. Check the queue worker and try a new preview when ready.';

        WeeklyMealDraft::query()->whereKey($this->draftId)
            ->whereIn('status', ['queued', 'generating'])
            ->update(['status' => 'failed', 'error' => $message, 'updated_at' => now()]);
    }
}
