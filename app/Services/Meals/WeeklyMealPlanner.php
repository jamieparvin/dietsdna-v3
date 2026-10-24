<?php

namespace App\Services\Meals;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyMealPlanner
{
    public function __construct(
        private readonly MealIdeaGenerator $generator,
        private readonly PlannedMealSnapshot $snapshots,
        private readonly WeeklyMealSchedule $schedule,
    ) {
    }

    public function source(User $user): array
    {
        $context = $this->generator->context($user, 'lunch');
        $profile = $user->nutritionProfile()->first();
        $slots = MealPlanSlots::forCount($context['meal_count']);

        // Include fields omitted by older generator signatures, e.g. cooking time.
        $signature = hash('sha256', json_encode([
            'version' => 'weekly-planning-v1',
            'generator_signature' => $context['signature'],
            'profile' => $profile?->getAttributes(),
            'slots' => $slots,
        ], JSON_THROW_ON_ERROR));

        return compact('context', 'slots', 'signature');
    }

    public function assertCurrent(WeeklyMealDraft $draft, User $user): array
    {
        $source = $this->source($user);

        if (! hash_equals($draft->source_signature, $source['signature'])) {
            $this->fail('Your profile, preferences or eligible ingredients changed. Generate a new weekly preview.');
        }

        return $source;
    }

    public function occupied(WeeklyMealDraft $draft): array
    {
        return MealPlanEntry::query()
            ->where('user_id', $draft->user_id)
            ->whereBetween('plan_date', [
                $draft->start_date->toDateString(),
                $draft->start_date->addDays(6)->toDateString(),
            ])
            ->get()
            ->map(fn ($entry) => $entry->plan_date->format('Y-m-d').'|'.$entry->slot)
            ->all();
    }

    public function generate(WeeklyMealDraft $draft): void
    {
        $user = User::query()->findOrFail($draft->user_id);
        $this->assertCurrent($draft, $user);
        $types = array_values(array_unique(array_map(
            fn ($slot) => WeeklyMealSchedule::typeForSlot($slot),
            array_keys($draft->slots)
        )));
        $pools = [];

        foreach ($types as $type) {
            if ($draft->fresh()->status !== 'generating') {
                return;
            }

            $this->assertCurrent($draft, $user);
            $batch = $this->generator->generate($user, $type);
            $source = $this->assertCurrent($draft, $user);

            $pools[$type] = array_map(
                fn ($idea) => $this->snapshots->fromIdea($idea, $source['context']['ingredients']),
                $batch['ideas']
            );

            WeeklyMealDraft::query()->whereKey($draft->id)->where('status', 'generating')->update([
                'generated' => json_encode($pools, JSON_THROW_ON_ERROR),
                'completed_types' => count($pools),
            ]);
        }

        $this->assertCurrent($draft, $user);
        $proposals = $this->schedule->build(
            $draft->start_date->toDateString(),
            $draft->slots,
            $pools,
            $this->occupied($draft)
        );

        WeeklyMealDraft::query()->whereKey($draft->id)->where('status', 'generating')->update([
            'proposals' => json_encode($proposals, JSON_THROW_ON_ERROR),
            'status' => 'ready',
        ]);
    }

    public function confirm(int $draftId, User $user): array
    {
        return DB::transaction(function () use ($draftId, $user) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            $draft = WeeklyMealDraft::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($draftId);

            if ($draft->status === 'confirmed') {
                return ['added' => 0, 'skipped' => 0, 'already_confirmed' => true];
            }

            if ($draft->status !== 'ready') {
                $this->fail('The weekly preview is not ready to save.');
            }

            $source = $this->assertCurrent($draft, $user);
            $added = 0;
            $skipped = 0;

            foreach ($draft->proposals ?? [] as $proposal) {
                // Existing meals and meals added while previewing are preserved.
                $occupied = MealPlanEntry::query()
                    ->where('user_id', $user->id)
                    ->whereDate('plan_date', $proposal['date'])
                    ->where('slot', $proposal['slot'])
                    ->exists();

                if ($occupied) {
                    $skipped++;
                    continue;
                }

                if (! $this->snapshots->eligible($proposal['snapshot'], $source['context']['ingredients'])) {
                    $this->fail('A proposed meal is no longer eligible. Generate a new preview.');
                }

                $saved = SavedMeal::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'content_hash' => $this->snapshots->hash($proposal['snapshot']),
                ], [
                    'meal_type' => $proposal['meal_type'],
                    'snapshot' => $proposal['snapshot'],
                ]);

                MealPlanEntry::query()->create([
                    'user_id' => $user->id,
                    'saved_meal_id' => $saved->id,
                    'plan_date' => $proposal['date'],
                    'slot' => $proposal['slot'],
                    'snapshot' => $proposal['snapshot'],
                ]);
                $added++;
            }

            $draft->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            return compact('added', 'skipped') + ['already_confirmed' => false];
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['week' => $message]);
    }
}
