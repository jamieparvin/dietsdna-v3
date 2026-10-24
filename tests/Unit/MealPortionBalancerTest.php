<?php

namespace Tests\Unit;

use App\Services\Meals\MealPortionBalancer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MealPortionBalancerTest extends TestCase
{
    private function food(float $energy, float $protein, float $carbs, float $fat, float $fibre = 0): array
    {
        return ['energy_kcal' => $energy, 'protein_g' => $protein, 'carbohydrate_g' => $carbs,
            'fat_g' => $fat, 'fibre_g' => $fibre, 'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0];
    }

    private function catalog(): array
    {
        // Synthetic independent nutrient sources, not real food recommendations.
        return [1 => $this->food(400, 100, 0, 0), 2 => $this->food(400, 0, 100, 0), 3 => $this->food(900, 0, 0, 100)];
    }

    private function proposals(): array
    {
        $rows = [];
        foreach (['lunch', 'dinner'] as $slot) {
            $rows[] = ['date' => '2026-10-26', 'slot' => $slot, 'meal_type' => $slot, 'snapshot' => [
                'name' => 'Synthetic fixture', 'instructions' => 'Prepare using the quantities in the ingredient list. Cook for 10 minutes.',
                'ingredients' => [
                    ['ingredient_id' => 1, 'quantity_g' => 20],
                    ['ingredient_id' => 2, 'quantity_g' => 40],
                    ['ingredient_id' => 3, 'quantity_g' => 10],
                ],
                'totals' => $this->food(99999, 99999, 99999, 99999), // Must be discarded and recomputed.
            ]];
        }
        return $rows;
    }

    private function targets(): array
    {
        return ['calories_kcal' => 1000, 'protein_g' => 50, 'carbohydrate_g' => 100, 'fat_g' => 400 / 9];
    }

    public function test_quantities_are_balanced_and_all_totals_are_recomputed_from_the_catalog(): void
    {
        $balancer = app(MealPortionBalancer::class);
        $result = $balancer->balance($this->proposals(), [], $this->catalog(), $this->targets());
        $totals = array_fill_keys(['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g'], 0.0);
        foreach ($result as $proposal) {
            foreach ($proposal['snapshot']['ingredients'] as $row) {
                $food = $this->catalog()[$row['ingredient_id']];
                $this->assertGreaterThan(0, $row['quantity_g']);
                foreach ($totals as $field => $unused) {
                    $totals[$field] += $food[$field] * $row['quantity_g'] / 100;
                }
                if ($row['ingredient_id'] === 3) {
                    $this->assertLessThanOrEqual(25, $row['quantity_g']);
                }
            }
            $this->assertSame(MealPortionBalancer::VERSION, $proposal['snapshot']['portion_balance']['version']);
            $this->assertSame('Synthetic fixture', $proposal['snapshot']['name']);
        }
        $this->assertEqualsWithDelta(1000, $totals['energy_kcal'], 50);
        $this->assertEqualsWithDelta(50, $totals['protein_g'], 5);
        $this->assertEqualsWithDelta(100, $totals['carbohydrate_g'], 10);
        $this->assertEqualsWithDelta(400 / 9, $totals['fat_g'], 400 / 90);
        $this->assertNotEquals($this->proposals()[0]['snapshot']['totals'], $result[0]['snapshot']['totals']);
        $balancer->assertFits($result, [], $this->targets());
    }

    public function test_existing_meals_are_fixed_and_count_towards_the_day(): void
    {
        $fixed = [['date' => '2026-10-26', 'slot' => 'breakfast', 'snapshot' => ['totals' => $this->food(200, 10, 20, 80 / 9)]]];
        $original = $fixed;
        $result = app(MealPortionBalancer::class)->balance($this->proposals(), $fixed, $this->catalog(), $this->targets());
        $this->assertSame($original, $fixed);
        app(MealPortionBalancer::class)->assertFits($result, $fixed, $this->targets());
    }

    public function test_missing_macro_sources_fail_instead_of_returning_a_short_week(): void
    {
        $catalog = $this->catalog();
        $catalog[3] = $this->food(0, 0, 0, 0);
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->balance($this->proposals(), [], $catalog, $this->targets());
    }

    public function test_database_energy_is_checked_independently_of_macro_energy(): void
    {
        $catalog = $this->catalog();
        foreach ($catalog as &$food) {
            $food['energy_kcal'] = 0;
        }
        unset($food);
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->balance($this->proposals(), [], $catalog, $this->targets());
    }

    public function test_total_carbohydrate_is_retained_and_available_carbohydrate_is_labelled_as_an_estimate(): void
    {
        $catalog = $this->catalog();
        $catalog[2]['carbohydrate_g'] = 110;
        $catalog[2]['fibre_g'] = 10;
        $result = app(MealPortionBalancer::class)->balance($this->proposals(), [], $catalog, $this->targets());
        foreach ($result as $proposal) {
            $totals = $proposal['snapshot']['totals'];
            $this->assertGreaterThan(0, $totals['fibre_g']);
            $this->assertEqualsWithDelta($totals['carbohydrate_g'] - $totals['fibre_g'], $totals['estimated_available_carbohydrate_g'], 0.000001);
        }
    }

    public function test_invalid_fibre_basis_is_rejected(): void
    {
        $catalog = $this->catalog();
        $catalog[1]['fibre_g'] = 5;
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->balance($this->proposals(), [], $catalog, $this->targets());
    }

    public function test_simple_gram_amounts_are_replaced_while_cooking_times_and_temperatures_are_preserved(): void
    {
        $proposals = $this->proposals();
        $proposals[0]['snapshot']['instructions'] = 'Cook 80g of dry couscous for 5 minutes. Grill 150g raw chicken at 180°C for 10–15 minutes. Chop 50g red peppers. Drizzle 10g olive oil.';
        $result = app(MealPortionBalancer::class)->balance($proposals, [], $this->catalog(), $this->targets());
        $method = $result[0]['snapshot']['instructions'];
        $this->assertSame('Cook the listed amount of dry couscous for 5 minutes. Grill the listed amount of raw chicken at 180°C for 10–15 minutes. Chop the listed amount of red peppers. Drizzle the listed amount of olive oil.', $method);
        $this->assertStringNotContainsString('of of', $method);
    }

    public function test_food_counts_are_still_rejected_instead_of_silently_changed(): void
    {
        $proposals = $this->proposals();
        $proposals[0]['snapshot']['instructions'] = 'Cook two eggs.';
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->balance($proposals, [], $this->catalog(), $this->targets());
    }

    public function test_mass_ratios_are_rejected_instead_of_corrupted(): void
    {
        $proposals = $this->proposals();
        $proposals[0]['snapshot']['instructions'] = 'Use 10 g per 100 g of the mixture.';
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->balance($proposals, [], $this->catalog(), $this->targets());
    }

    public function test_missing_daily_targets_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->balance($this->proposals(), [], $this->catalog(), []);
    }

    public function test_confirm_check_rejects_a_day_that_has_changed_since_balancing(): void
    {
        $balancer = app(MealPortionBalancer::class);
        $result = $balancer->balance($this->proposals(), [], $this->catalog(), $this->targets());
        $fixed = [['date' => '2026-10-26', 'slot' => 'breakfast', 'snapshot' => ['totals' => $this->food(1000, 50, 100, 400 / 9)]]];
        $this->expectException(ValidationException::class);
        $balancer->assertFits($result, $fixed, $this->targets());
    }
}
