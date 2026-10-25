<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Services\Meals\MealIdeaPortionBalancer;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MealIdeaPortionBalancerTest extends TestCase
{
    private function context(): array
    {
        $ingredients = collect();
        foreach ([[400,100,0,0],[400,0,110,0],[900,0,0,100]] as $i => [$energy,$protein,$carbs,$fat]) {
            $food = new Ingredient();
            $food->setRawAttributes([
                'id' => $i + 1, 'name' => 'Synthetic nutrient '.$i, 'category' => 'Test', 'preference_food' => null,
                'preparation_state' => 'raw', 'energy_kcal' => $energy, 'protein_g' => $protein,
                'carbohydrate_g' => $carbs, 'fat_g' => $fat, 'fibre_g' => $i === 1 ? 10 : 0,
                'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0,
            ]);
            $ingredients->push($food);
        }
        return ['signature' => 'single-test', 'meal_type' => 'breakfast', 'ingredients' => $ingredients,
            'meal_targets' => ['energy_kcal' => 500, 'protein_g' => 25, 'carbohydrate_g' => 50, 'fat_g' => 200/9]];
    }

    private function batch(array $context): array
    {
        $ideas = [];
        foreach (range(1,3) as $n) {
            $ideas[] = ['name' => 'Alternative '.$n, 'description' => 'Synthetic fixture.',
                'instructions' => 'Cook the ingredients for 10 minutes.',
                'ingredients' => $context['ingredients']->map(fn ($food, $i) => [
                    'name' => $food->name, 'preparation_state' => $food->preparation_state,
                    'quantity_g' => [20,40,10][$i],
                ])->all(), 'totals' => [], 'calorie_difference' => -9999];
        }
        return ['signature' => $context['signature'], 'meal_type' => $context['meal_type'], 'created_at' => now()->timestamp, 'ideas' => $ideas];
    }

    public function test_each_alternative_receives_a_whole_meal_budget_not_one_third_of_it(): void
    {
        Http::preventStrayRequests();
        $context = $this->context();
        $batch = app(MealIdeaPortionBalancer::class)->balanceBatch($this->batch($context), $context);
        $this->assertCount(3, $batch['ideas']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertEqualsWithDelta(500, $idea['totals']['energy_kcal'], 25);
            $this->assertEqualsWithDelta(25, $idea['totals']['protein_g'], 2.5);
            $this->assertEqualsWithDelta(50, $idea['totals']['estimated_available_carbohydrate_g'], 5);
            $this->assertEqualsWithDelta(200/9, $idea['totals']['fat_g'], 200/90);
            $this->assertArrayNotHasKey('portion_balance', $idea);
            $this->assertSame(MealIdeaPortionBalancer::VERSION, $idea['idea_portion_balance']['version']);
        }
    }

    public function test_totals_and_calorie_difference_are_recomputed_after_rounding(): void
    {
        $context = $this->context();
        $batch = app(MealIdeaPortionBalancer::class)->balanceBatch($this->batch($context), $context);
        $catalog = $context['ingredients']->keyBy('id');
        foreach ($batch['ideas'] as $idea) {
            foreach (['energy_kcal','protein_g','carbohydrate_g','sugars_g','fat_g','saturates_g','fibre_g','salt_g'] as $field) {
                $total = 0;
                foreach ($idea['ingredients'] as $row) { $total += $catalog[$row['ingredient_id']]->{$field} * $row['quantity_g'] / 100; }
                $this->assertEqualsWithDelta($total, $idea['totals'][$field], 1e-6);
            }
            $this->assertEqualsWithDelta($idea['totals']['energy_kcal'] - 500, $idea['calorie_difference'], 1e-6);
        }
    }

    public function test_unfit_options_are_omitted_and_remaining_indexes_are_contiguous(): void
    {
        $context = $this->context();
        $raw = $this->batch($context);
        array_pop($raw['ideas'][0]['ingredients']); // No fat source for this option.
        $batch = app(MealIdeaPortionBalancer::class)->balanceBatch($raw, $context);
        $this->assertSame([0,1], array_keys($batch['ideas']));
        $this->assertSame('Alternative 2', $batch['ideas'][0]['name']);
        $this->assertSame(1, $batch['rejected_count']);
        $this->assertSame(3, $batch['requested_count']);
    }

    public function test_a_batch_with_no_fitting_options_is_rejected(): void
    {
        $context = $this->context();
        $raw = $this->batch($context);
        foreach ($raw['ideas'] as &$idea) { array_pop($idea['ingredients']); }
        unset($idea);
        $this->expectException(ValidationException::class);
        app(MealIdeaPortionBalancer::class)->balanceBatch($raw, $context);
    }

    public function test_changed_context_is_rejected_before_portion_adjustment(): void
    {
        $context = $this->context();
        $raw = $this->batch($context);
        $context['signature'] = 'changed';
        $this->expectException(ValidationException::class);
        app(MealIdeaPortionBalancer::class)->balanceBatch($raw, $context);
    }

    public function test_save_revalidation_does_not_change_displayed_quantities(): void
    {
        $context = $this->context();
        $service = app(MealIdeaPortionBalancer::class);
        $batch = $service->balanceBatch($this->batch($context), $context);
        $before = $batch['ideas'][0];
        $service->assertSavable($batch['ideas'][0], $context);
        $this->assertSame($before, $batch['ideas'][0]);
    }

    public function test_save_revalidation_rejects_inconsistent_nutrition(): void
    {
        $context = $this->context();
        $service = app(MealIdeaPortionBalancer::class);
        $idea = $service->balanceBatch($this->batch($context), $context)['ideas'][0];
        $idea['totals']['energy_kcal'] += 100;
        $this->expectException(ValidationException::class);
        $service->assertSavable($idea, $context);
    }

    public function test_screenshot_examples_do_not_display_low_fat_options_as_matching_the_guide(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/single-meal-portions.json'), true, 512, JSON_THROW_ON_ERROR);
        $foods = collect($fixture['catalog'])->map(function ($attributes) { $food = new Ingredient(); $food->setRawAttributes($attributes); return $food; })->values();
        $context = ['signature' => 'screenshot-fixture', 'meal_type' => 'breakfast', 'ingredients' => $foods, 'meal_targets' => $fixture['targets']];
        $batch = app(MealIdeaPortionBalancer::class)->balanceBatch([
            'signature' => $context['signature'], 'meal_type' => 'breakfast', 'created_at' => now()->timestamp, 'ideas' => $fixture['ideas'],
        ], $context);
        $this->assertCount(1, $batch['ideas']);
        $this->assertSame('Turkey and spinach omelette', $batch['ideas'][0]['name']);
        $this->assertSame(2, $batch['rejected_count']);
        foreach ($fixture['targets'] as $field => $target) {
            $actual = $batch['ideas'][0]['totals'][$field === 'carbohydrate_g' ? 'estimated_available_carbohydrate_g' : $field];
            $this->assertEqualsWithDelta($target, $actual, $target * ($field === 'energy_kcal' ? .05 : .1));
        }
    }
    public function test_eligible_fat_sources_make_the_other_screenshot_options_feasible(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/single-meal-portions.json'), true, 512, JSON_THROW_ON_ERROR);
        $foods = collect($fixture['catalog'])->map(function ($attributes) { $food = new Ingredient(); $food->setRawAttributes($attributes); return $food; })->values();
        $context = ['signature' => 'fat-source-fixture', 'meal_type' => 'breakfast', 'ingredients' => $foods, 'meal_targets' => $fixture['targets']];
        $batch = app(MealIdeaPortionBalancer::class)->balanceBatch([
            'signature' => $context['signature'], 'meal_type' => 'breakfast', 'created_at' => now()->timestamp, 'ideas' => $fixture['fat_augmented_ideas'],
        ], $context);
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame(0, $batch['rejected_count']);
        $this->assertContains(77, array_column($batch['ideas'][1]['ingredients'], 'ingredient_id'));
        $this->assertContains(87, array_column($batch['ideas'][2]['ingredients'], 'ingredient_id'));
        foreach ($batch['ideas'] as $idea) {
            $this->assertEqualsWithDelta(16.05, $idea['totals']['fat_g'], 1.605);
            app(MealIdeaPortionBalancer::class)->assertSavable($idea, $context);
        }
    }

}
