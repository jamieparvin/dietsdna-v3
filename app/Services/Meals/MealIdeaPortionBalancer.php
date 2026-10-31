<?php

namespace App\Services\Meals;

use Illuminate\Validation\ValidationException;

class MealIdeaPortionBalancer
{
    public const VERSION = 'single-meal-portions-v1';

    private const NUTRIENTS = ['energy_kcal', 'protein_g', 'carbohydrate_g', 'sugars_g', 'fat_g', 'saturates_g', 'fibre_g', 'salt_g'];

    public function __construct(
        private readonly MealPortionBalancer $balancer,
        private readonly PlannedMealSnapshot $snapshots,
    ) {}

    public function isCurrentBatch(array $batch): bool
    {
        if (($batch['portion_version'] ?? null) !== self::VERSION
            || ($batch['balancer_version'] ?? null) !== MealPortionBalancer::VERSION
            || ! is_array($batch['ideas'] ?? null) || ! count($batch['ideas']) || count($batch['ideas']) > 3) {
            return false;
        }
        foreach ($batch['ideas'] as $idea) {
            if (! is_array($idea) || ($idea['idea_portion_balance']['version'] ?? null) !== self::VERSION
                || ($idea['idea_portion_balance']['balancer_version'] ?? null) !== MealPortionBalancer::VERSION) {
                return false;
            }
        }

        return true;
    }

    /** Each alternative is balanced separately; alternatives never share a budget. */
    public function balanceBatch(array $batch, array $context, bool $allowEmpty = false): array
    {
        if (($batch['signature'] ?? null) !== $context['signature']
            || ($batch['meal_type'] ?? null) !== $context['meal_type']) {
            $this->fail('Your profile, preferences or ingredients changed. Generate fresh ideas.');
        }
        if (! is_array($batch['ideas'] ?? null) || ! count($batch['ideas']) || count($batch['ideas']) > 3) {
            $this->fail('No valid meal ideas were available to portion-check.');
        }
        $targets = $this->targets($context);
        $catalog = $context['ingredients']->mapWithKeys(fn ($ingredient) => [(int) $ingredient->id => $ingredient->getAttributes()])->all();
        $ideas = [];
        $rejections = [];
        $requested = count($batch['ideas']);
        foreach ($batch['ideas'] as $idea) {
            try {
                $snapshot = $this->snapshots->fromIdea($idea, $context['ingredients']);
                $balanced = $this->balancer->balance([$this->proposal($snapshot, $context)], [], $catalog, $targets);
                $snapshot = $balanced[0]['snapshot'];
                // A single-meal guide must not masquerade as weekly daily targets.
                unset($snapshot['portion_balance']);
                $snapshot['idea_portion_balance'] = [
                    'version' => self::VERSION,
                    'balancer_version' => MealPortionBalancer::VERSION,
                    'meal_type' => $context['meal_type'],
                    'targets' => $targets,
                    'carbohydrate_basis' => 'estimated_available_total_minus_fibre',
                ];
                $snapshot['calorie_difference'] = $snapshot['totals']['energy_kcal'] - $targets['calories_kcal'];
                $this->assertSavable($snapshot, $context);
                $ideas[] = $snapshot;
            } catch (ValidationException $exception) {
                // Omit an unsuitable alternative rather than save a short portion,
                // invent an ingredient or relax the guides. A caller may request replacements.
                $rejections[] = [
                    'name' => $idea['name'],
                    'reason' => mb_substr(collect($exception->errors())->flatten()->first(), 0, 500),
                ];
            }
        }
        if (! $ideas && ! $allowEmpty) {
            $this->fail('None of these ideas could meet this meal’s calorie and macro guide within the portion limits. No new meals were saved and no automatic retry was made. Try different meal ideas.');
        }
        $batch['ideas'] = array_values($ideas);
        $batch['portion_version'] = self::VERSION;
        $batch['balancer_version'] = MealPortionBalancer::VERSION;
        $batch['requested_count'] = $requested;
        $batch['rejected_count'] = $requested - count($ideas);
        $batch['portion_rejections'] = $rejections;

        return $batch;
    }

    /** Validate, but NEVER resize, the portion already displayed in the session. */
    public function assertSavable(array $idea, array $context): void
    {
        if (($idea['idea_portion_balance']['version'] ?? null) !== self::VERSION
            || ($idea['idea_portion_balance']['balancer_version'] ?? null) !== MealPortionBalancer::VERSION
            || ($idea['idea_portion_balance']['meal_type'] ?? null) !== $context['meal_type']
            || ! $this->snapshots->eligible($idea, $context['ingredients'])) {
            $this->fail('This idea needs a fresh portion check. Generate a new batch before saving.');
        }
        $catalog = $context['ingredients']->keyBy('id');
        $totals = array_fill_keys(self::NUTRIENTS, 0.0);
        $mass = 0.0;
        $seen = [];
        foreach ($idea['ingredients'] as $row) {
            $food = $catalog->get($row['ingredient_id']);
            $quantity = $row['quantity_g'] ?? null;
            if (! is_numeric($quantity) || ! is_finite((float) $quantity) || $quantity <= 0 || $quantity > 1000
                || abs($quantity * 100 - round($quantity * 100)) > 1e-6 || isset($seen[$food->id])) {
                $this->fail('A displayed ingredient portion is invalid. Generate fresh ideas.');
            }
            $seen[$food->id] = true;
            $mass += $quantity;
            foreach (self::NUTRIENTS as $field) {
                $value = $food->{$field};
                if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) {
                    $this->fail('An ingredient no longer has complete nutrition. Generate fresh ideas.');
                }
                $totals[$field] += (float) $value * $quantity / 100;
            }
        }
        if ($mass > ($context['meal_type'] === 'snack' ? 500 : 1100) + 1e-8) {
            $this->fail('This portion exceeds the meal weight limit. Generate fresh ideas.');
        }
        $totals['estimated_available_carbohydrate_g'] = max(0, $totals['carbohydrate_g'] - $totals['fibre_g']);
        foreach ($totals as $field => $value) {
            $stored = $idea['totals'][$field] ?? null;
            if (! is_numeric($stored) || ! is_finite((float) $stored) || abs($value - $stored) > 1e-6) {
                $this->fail('This idea’s nutrition no longer matches its displayed ingredients. Generate fresh ideas.');
            }
        }
        $checked = $idea;
        $checked['totals'] = $totals;
        // Reuse the existing 5% calorie / 10% macro checks against THIS MEAL,
        // including estimated available carbohydrate. No optimization on save.
        $this->balancer->assertFits([$this->proposal($checked, $context)], [], $this->targets($context));
    }

    private function targets(array $context): array
    {
        $targets = [];
        foreach (['calories_kcal' => 'energy_kcal', 'protein_g' => 'protein_g', 'carbohydrate_g' => 'carbohydrate_g', 'fat_g' => 'fat_g'] as $field => $source) {
            $value = $context['meal_targets'][$source] ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value) || $value <= 0) {
                $this->fail('A complete meal calorie and macro guide is required.');
            }
            $targets[$field] = (float) $value;
        }

        return $targets;
    }

    private function proposal(array $snapshot, array $context): array
    {
        return [
            'date' => now('Europe/London')->toDateString(),
            'slot' => $context['meal_type'],
            'meal_type' => $context['meal_type'],
            'snapshot' => $snapshot,
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['generation' => $message]);
    }
}
