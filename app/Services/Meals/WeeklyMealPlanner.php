<?php

namespace App\Services\Meals;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyMealPlanner
{
    public function __construct(
        private readonly MealIdeaGenerator $generator,
        private readonly PlannedMealSnapshot $snapshots,
        private readonly WeeklyMealSchedule $schedule,
        private readonly MealPortionBalancer $balancer,
        private readonly MealPlanWeekState $weeks,
    ) {}

    public function source(User $user): array
    {
        $context = $this->generator->context($user, 'lunch');
        $profile = $user->nutritionProfile()->first();
        $slots = MealPlanSlots::forCount(
            $context['meal_count'],
            $context['snack_count'] ?? 0
        );

        $profileAttributes = $profile?->getAttributes() ?? [];
        // Retain legacy decimal text when added precision only pads existing kg values.
        foreach (['weight_kg', 'target_weight_kg'] as $field) {
            if (isset($profileAttributes[$field]) && is_string($profileAttributes[$field])) {
                $profileAttributes[$field] = preg_replace('/^(-?\d+\.\d{2})0+$/', '$1', $profileAttributes[$field]);
            }
        }

        // Include fields omitted by older generator signatures, e.g. cooking time.
        $signatureInput = [
            'version' => 'weekly-planning-v3-full-meals',
            'portion_balance' => MealPortionBalancer::VERSION,
            'generator_signature' => $context['signature'],
            // The retired dietary field and its timestamp are not planning inputs.
            'profile' => array_diff_key($profileAttributes, array_flip(['dietary_preference', 'updated_at'])),
            'slots' => $slots,
        ];
        $previousSignature = hash('sha256', json_encode($signatureInput, JSON_THROW_ON_ERROR));
        $previousProfile = $signatureInput['profile'];
        $signatureInput['profile'] = array_diff_key($previousProfile, array_flip(['planning_context', 'planning_circumstances']));
        $signature = hash('sha256', json_encode($signatureInput, JSON_THROW_ON_ERROR));
        $signatureInput['portion_balance'] = 'portion-balance-v1';
        $canonicalLegacySignature = hash('sha256', json_encode($signatureInput, JSON_THROW_ON_ERROR));
        $signatureInput['profile'] = $previousProfile;
        $legacySignature = hash('sha256', json_encode($signatureInput, JSON_THROW_ON_ERROR));

        return compact('context', 'slots', 'signature', 'previousSignature', 'legacySignature', 'canonicalLegacySignature');
    }

    public function assertCurrent(WeeklyMealDraft $draft, User $user): array
    {
        $source = $this->source($user);

        if (! in_array($draft->source_signature, [$source['signature'], $source['previousSignature']], true)) {
            $this->fail('Your profile, preferences or eligible ingredients changed. Generate a new weekly preview.');
        }

        return $source;
    }

    public function occupied(WeeklyMealDraft $draft): array
    {
        return $this->weeks->query($draft->user_id, $draft->start_date)
            ->get()
            ->map(fn ($entry) => $entry->plan_date->format('Y-m-d').'|'.$entry->slot)
            ->all();
    }

    /** Two-meal patterns permit at most one replacement batch per main type, for portion repairs. */
    public function generate(WeeklyMealDraft $draft): void
    {
        $user = User::query()->findOrFail($draft->user_id);
        $this->assertCurrent($draft, $user);
        $this->assertReplacementBaseline($draft, $this->planState($draft));
        $types = array_values(array_unique(array_map(
            fn ($slot) => WeeklyMealSchedule::typeForSlot($slot),
            array_keys($draft->slots)
        )));
        $pools = [];
        $repairedTypes = [];
        $state = $this->planState($draft);
        $fixed = $draft->mode === 'replace' ? [] : $state['fixed'];
        $usage = $this->proteinUsage($fixed);

        foreach ($types as $type) {
            if ($draft->fresh()->status !== 'generating') {
                return;
            }

            $this->assertCurrent($draft, $user);
            $this->assertReplacementBaseline($draft, $this->planState($draft));
            $batch = $this->generator->generate($user, $type, 3, [], [], $usage);
            $source = $this->assertCurrent($draft, $user);

            $pools[$type] = array_map(
                fn ($idea) => $this->snapshots->fromIdea($idea, $source['context']['ingredients']),
                $batch['ideas']
            );
            $catalog = $source['context']['ingredients']->mapWithKeys(fn ($food) => [$food->id => $food->getAttributes()])->all();
            foreach (app(ProteinVariety::class)->usage(array_map(fn ($snapshot) => ['snapshot' => $snapshot], $pools[$type]), $catalog) as $key => $count) {
                $usage[$key] = ($usage[$key] ?? 0) + $count;
            }

            WeeklyMealDraft::query()->whereKey($draft->id)->where('status', 'generating')->update([
                'generated' => json_encode($pools, JSON_THROW_ON_ERROR),
                'completed_types' => count($pools),
            ]);
        }

        try {
            $this->preparePreview($draft, $user, $pools, 'generating');
        } catch (ValidationException $failure) {
            if ($draft->meal_count !== 2) {
                throw $failure;
            }
            $repaired = false;
            foreach (['lunch', 'dinner'] as $type) {
                if (isset($repairedTypes[$type]) || ! isset($pools[$type])) {
                    continue;
                }
                if ($draft->fresh()->status !== 'generating') {
                    return;
                }
                $this->assertCurrent($draft, $user);
                $this->assertReplacementBaseline($draft, $this->planState($draft));
                $context = $this->generator->context($user, $type);
                $checked = app(MealIdeaPortionBalancer::class)->balanceBatch(['signature' => $context['signature'], 'meal_type' => $type, 'ideas' => $pools[$type]], $context, true);
                if ($checked['ideas']) {
                    continue;
                }
                $batch = $this->generator->generate($user, $type, 3, array_column($pools[$type], 'name'), $checked['portion_rejections'], $usage);
                $this->assertCurrent($draft, $user);
                $ideas = $batch['ideas'];
                $pools[$type] = array_map(fn ($idea) => $this->snapshots->fromIdea($idea, $context['ingredients']), $ideas);
                $repairedTypes[$type] = true;
                $repaired = true;
            }
            if (! $repaired) {
                throw $failure;
            }
            WeeklyMealDraft::query()->whereKey($draft->id)->where('status', 'generating')->update(['generated' => json_encode($pools, JSON_THROW_ON_ERROR)]);
            $this->preparePreview($draft, $user, $pools, 'generating');
        }
    }

    /** Rebuild an unconfirmed stored preview. Never calls AI or writes planned/saved meals. */
    public function rebalanceStored(WeeklyMealDraft $draft): void
    {
        $lock = Cache::lock('meal-ideas:user:'.$draft->user_id, 600);
        if (! $lock->get()) {
            $this->fail('Meal generation is already running for this user. Wait before rebalancing stored options.');
        }
        $claimed = false;
        try {
            $draft->refresh();
            if (! in_array($draft->status, ['failed', 'ready'], true)) {
                $this->fail('Only a failed or ready unconfirmed preview can be rebuilt.');
            }
            $user = User::query()->findOrFail($draft->user_id);
            $source = $this->source($user);
            if (! in_array($draft->source_signature, [$source['signature'], $source['previousSignature'], $source['legacySignature'], $source['canonicalLegacySignature']], true)) {
                $this->fail('Your profile, preferences or eligible ingredients changed. Generate a new weekly preview.');
            }
            $pools = $draft->generated ?? [];
            $types = array_unique(array_map(
                fn ($slot) => WeeklyMealSchedule::typeForSlot($slot),
                array_keys($draft->slots)
            ));
            foreach ($types as $type) {
                if (! isset($pools[$type]) || ! is_array($pools[$type]) || count($pools[$type]) < 1) {
                    $this->fail('This draft does not contain a complete set of stored options. No AI request was made.');
                }
                foreach ($pools[$type] as $snapshot) {
                    if (! $this->snapshots->eligible($snapshot, $source['context']['ingredients'])) {
                        $this->fail('A stored meal is no longer eligible. No AI request was made.');
                    }
                }
            }
            // The same user/draft locks as confirmation prevent an already confirmed
            // preview being claimed. While generating, confirmation is rejected.
            DB::transaction(function () use ($draft, $source, $user) {
                DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
                $locked = WeeklyMealDraft::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($draft->id);
                if (! in_array($locked->status, ['failed', 'ready'], true)
                    || $locked->source_signature !== $draft->source_signature) {
                    $this->fail('This preview changed while rebuilding was requested. Reload it.');
                }
                $locked->update(['status' => 'generating', 'source_signature' => $source['signature'], 'error' => null]);
            });
            $claimed = true;
            $draft->refresh();
            $this->preparePreview($draft, $user, $pools, 'generating');
        } catch (\Throwable $exception) {
            if ($claimed) {
                $message = $exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first()
                    : 'Stored options could not be rebuilt. No new meals were saved.';
                WeeklyMealDraft::query()->whereKey($draft->id)->where('status', 'generating')->update([
                    'status' => 'failed', 'error' => $message,
                ]);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function preparePreview(WeeklyMealDraft $draft, User $user, array $pools, string $expectedStatus): void
    {
        $this->assertCurrent($draft, $user);
        $state = $this->planState($draft);
        $this->assertReplacementBaseline($draft, $state);
        $fixed = $draft->mode === 'replace' ? [] : $state['fixed'];
        $source = $this->assertCurrent($draft, $user);
        $targets = array_intersect_key($source['context']['targets'], array_flip([
            'calories_kcal', 'protein_g', 'carbohydrate_g', 'fat_g',
        ]));
        $catalog = $source['context']['ingredients']->mapWithKeys(
            fn ($ingredient) => [(int) $ingredient->id => $ingredient->getAttributes()]
        )->all();
        try {
            $proposals = $this->schedule->buildBalanced(
                $draft->start_date->toDateString(), $draft->slots, $pools,
                $fixed, $catalog, $targets, $this->balancer, $this->proteinUsage($fixed), $source['context']['cooking_minutes'] ?? null
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['week' => 'No suitable preview was found for your '.$draft->meal_count.' full meals per day within the portion limits. Try a fresh preview to request different combinations. The available ingredient library may need review if this continues. Your daily targets, saved meals and existing plan have not changed. '.collect($exception->errors())->flatten()->first()]);
        }
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

    private function proteinUsage(array $rows): array
    {
        // Historical foods can now be excluded/inactive but still count towards variety.
        $ids = collect($rows)->pluck('snapshot.ingredients')->flatten(1)->pluck('ingredient_id')->filter()->unique();
        $catalog = Ingredient::query()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($food) => [$food->id => $food->getAttributes()])->all();

        return app(ProteinVariety::class)->usage($rows, $catalog);
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
                return ['added' => 0, 'skipped' => 0, 'removed' => 0, 'already_confirmed' => true];
            }

            if ($draft->status !== 'ready') {
                $this->fail('The weekly preview is not ready to save.');
            }

            $source = $this->assertCurrent($draft, $user);
            $state = $this->planState($draft);
            $this->assertReplacementBaseline($draft, $state);
            $replacing = $draft->mode === 'replace';
            $proposals = $draft->proposals ?? [];
            if ($replacing) {
                $this->assertCompleteReplacement($draft, $proposals, $source['slots']);
            }
            foreach ($proposals as $proposal) {
                if (($proposal['plan_fingerprint'] ?? null) !== $state['fingerprint']) {
                    $this->fail('Your meal plan changed after this preview was prepared. Generate a new preview to balance it around your current meals. Nothing was saved.');
                }
            }
            $this->balancer->assertFits($proposals, $replacing ? [] : $state['fixed'], $source['context']['targets']);
            // Validate EVERY meal before deleting any existing entry.
            foreach ($proposals as $proposal) {
                if (! $this->snapshots->eligible($proposal['snapshot'], $source['context']['ingredients'])) {
                    $this->fail('A proposed meal is no longer eligible. Generate a new preview.');
                }
            }
            $removed = 0;
            if ($replacing) {
                $removed = $this->weeks->query($user->id, $draft->start_date)->delete();
            }
            $added = 0;
            $skipped = 0;

            foreach ($proposals as $proposal) {
                // Fill mode preserves occupied slots; replacement has atomically cleared this week.
                $occupied = MealPlanEntry::query()
                    ->where('user_id', $user->id)
                    ->whereDate('plan_date', $proposal['date'])
                    ->where('slot', $proposal['slot'])
                    ->exists();

                if ($occupied) {
                    if ($replacing) {
                        $this->fail('A replacement slot could not be written. The transaction was rolled back.');
                    }
                    $skipped++;

                    continue;
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

            if ($replacing) {
                WeeklyMealDraft::query()->where('user_id', $user->id)->where('id', '!=', $draft->id)
                    ->whereDate('start_date', $draft->start_date->toDateString())
                    ->whereIn('status', ['queued', 'generating', 'ready'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            $draft->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            return compact('added', 'skipped', 'removed') + ['already_confirmed' => false];
        });
    }

    private function planState(WeeklyMealDraft $draft): array
    {
        return $this->weeks->capture($draft->user_id, $draft->start_date);
    }

    private function assertReplacementBaseline(WeeklyMealDraft $draft, array $state): void
    {
        $mode = $draft->mode ?? 'fill';
        if (! in_array($mode, ['fill', 'replace'], true)) {
            $this->fail('Choose a valid weekly planning mode.');
        }
        if ($mode === 'replace' && (! is_string($draft->baseline_fingerprint)
            || ! hash_equals($state['fingerprint'], $draft->baseline_fingerprint))) {
            $this->fail('Your meal plan changed after this replacement was requested. Generate a new replacement preview. Your current meals were not changed.');
        }
    }

    private function assertCompleteReplacement(WeeklyMealDraft $draft, array $proposals, array $currentSlots): void
    {
        if (! $currentSlots || $draft->slots != $currentSlots || count($proposals) !== 7 * count($currentSlots)) {
            $this->fail('The replacement does not contain every slot for the week. Nothing was replaced.');
        }
        $expected = [];
        foreach (range(0, 6) as $day) {
            $date = $draft->start_date->addDays($day)->toDateString();
            foreach ($currentSlots as $slot => $unused) {
                $expected[$date.'|'.$slot] = WeeklyMealSchedule::typeForSlot($slot);
            }
        }
        foreach ($proposals as $proposal) {
            $key = ($proposal['date'] ?? '').'|'.($proposal['slot'] ?? '');
            if (! isset($expected[$key]) || ($proposal['meal_type'] ?? null) !== $expected[$key]) {
                $this->fail('The replacement contains an invalid or repeated meal slot. Nothing was replaced.');
            }
            unset($expected[$key]);
        }
        if ($expected) {
            $this->fail('The replacement is missing meal slots. Nothing was replaced.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['week' => $message]);
    }
}
