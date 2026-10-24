<?php

namespace App\Services\Meals;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class WeeklyMealPlanner
{
    public function __construct(
        private readonly MealIdeaGenerator $generator,
        private readonly PlannedMealSnapshot $snapshots,
        private readonly WeeklyMealSchedule $schedule,
        private readonly MealPortionBalancer $balancer,
    ) {
    }

    public function source(User $user): array
    {
        $context = $this->generator->context($user, 'lunch');
        $profile = $user->nutritionProfile()->first();
        $slots = MealPlanSlots::forCount(
            $context['meal_count'],
            $context['snack_count'] ?? 0
        );

        // Include fields omitted by older generator signatures, e.g. cooking time.
        $signature = hash('sha256', json_encode([
            'version' => 'weekly-planning-v3-full-meals',
            'portion_balance' => MealPortionBalancer::VERSION,
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

        $this->preparePreview($draft, $user, $pools, 'generating');
    }

    /** Reuse a failed draft's already generated options. Never calls AI. */
    public function rebalanceStored(WeeklyMealDraft $draft): void
    {
        $lock = Cache::lock('meal-ideas:user:'.$draft->user_id, 120);
        if (! $lock->get()) {
            $this->fail('Meal generation is already running for this user. Wait before rebalancing stored options.');
        }
        try {
            $draft->refresh();
            if ($draft->status !== 'failed') {
                $this->fail('Only a failed preview can be rebuilt from stored options.');
            }
            $user = User::query()->findOrFail($draft->user_id);
            $source = $this->assertCurrent($draft, $user);
            $pools = $draft->generated ?? [];
            $types = array_unique(array_map(
                fn ($slot) => WeeklyMealSchedule::typeForSlot($slot),
                array_keys($draft->slots)
            ));
            foreach ($types as $type) {
                if (! isset($pools[$type]) || ! is_array($pools[$type]) || count($pools[$type]) < 3) {
                    $this->fail('This draft does not contain a complete set of stored options. No AI request was made.');
                }
                foreach ($pools[$type] as $snapshot) {
                    if (! $this->snapshots->eligible($snapshot, $source['context']['ingredients'])) {
                        $this->fail('A stored meal is no longer eligible. No AI request was made.');
                    }
                }
            }
            $this->preparePreview($draft, $user, $pools, 'failed');
        } catch (ValidationException $exception) {
            WeeklyMealDraft::query()->whereKey($draft->id)->where('status', 'failed')->update([
                'error' => collect($exception->errors())->flatten()->first(),
            ]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function preparePreview(WeeklyMealDraft $draft, User $user, array $pools, string $expectedStatus): void
    {
        $this->assertCurrent($draft, $user);
        $state = $this->planState($draft);
        $proposals = $this->schedule->build(
            $draft->start_date->toDateString(),
            $draft->slots,
            $pools,
            array_map(fn ($entry) => $entry['date'].'|'.$entry['slot'], $state['fixed'])
        );

        $source = $this->assertCurrent($draft, $user);
        $targets = array_intersect_key($source['context']['targets'], array_flip([
            'calories_kcal', 'protein_g', 'carbohydrate_g', 'fat_g',
        ]));
        $catalog = $source['context']['ingredients']->mapWithKeys(
            fn ($ingredient) => [(int) $ingredient->id => $ingredient->getAttributes()]
        )->all();
        $proposals = $this->balancer->balance($proposals, $state['fixed'], $catalog, $targets);
        foreach ($proposals as &$proposal) {
            $proposal['plan_fingerprint'] = $state['fingerprint'];
        }
        unset($proposal);
        $this->assertCurrent($draft, $user);

        WeeklyMealDraft::query()->whereKey($draft->id)->where('status', $expectedStatus)->update([
            'proposals' => json_encode($proposals, JSON_THROW_ON_ERROR),
            'status' => 'ready',
            'error' => null,
            'completed_types' => count($pools),
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
            $state = $this->planState($draft);
            foreach ($draft->proposals ?? [] as $proposal) {
                if (($proposal['plan_fingerprint'] ?? null) !== $state['fingerprint']) {
                    $this->fail('Your meal plan changed after this preview was prepared. Generate a new preview to balance it around your current meals. Nothing was saved.');
                }
            }
            $this->balancer->assertFits($draft->proposals ?? [], $state['fixed'], $source['context']['targets']);
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

    private function planState(WeeklyMealDraft $draft): array
    {
        $entries = MealPlanEntry::query()
            ->where('user_id', $draft->user_id)
            ->whereBetween('plan_date', [
                $draft->start_date->toDateString(),
                $draft->start_date->addDays(6)->toDateString(),
            ])->orderBy('id')->get();
        $fixed = $entries->map(fn ($entry) => [
            'date' => $entry->plan_date->format('Y-m-d'),
            'slot' => $entry->slot,
            'snapshot' => $entry->snapshot,
        ])->all();
        $fingerprint = hash('sha256', json_encode($entries->map(fn ($entry) => [
            'id' => $entry->id, 'slot' => $entry->slot,
            'date' => $entry->plan_date->format('Y-m-d'),
            'saved_meal_id' => $entry->saved_meal_id,
            'snapshot' => $entry->snapshot,
        ])->all(), JSON_THROW_ON_ERROR));
        return compact('fixed', 'fingerprint');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['week' => $message]);
    }
}
