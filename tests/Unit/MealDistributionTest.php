<?php

namespace Tests\Unit;

use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\WeeklyMealSchedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MealDistributionTest extends TestCase
{
    private function targets(): array
    {
        return ['calories_kcal' => 3210, 'protein_g' => 240.75, 'carbohydrate_g' => 361.125, 'fat_g' => 89.1666666667];
    }

    private function slots(int $main = 5, int $snacks = 1): array
    {
        $rows = [];
        foreach (array_slice(['breakfast', 'lunch', 'dinner', 'meal_2', 'meal_4', 'meal_5'], 0, $main) as $slot) {
            $rows[] = ['date' => '2026-11-09', 'slot' => $slot];
        }
        for ($i = 1; $i <= $snacks; $i++) {
            $rows[] = ['date' => '2026-11-09', 'slot' => 'snack_'.$i];
        }
        return $rows;
    }

    public function test_five_full_meals_share_ninety_percent_and_one_optional_snack_gets_ten_percent(): void
    {
        $shares = app(MealPortionBalancer::class)->mealTargets($this->slots(), [], $this->targets());
        foreach (array_slice($shares, 0, 5) as $share) {
            $this->assertEqualsWithDelta(577.8, $share['calories_kcal'], 1e-6);
            $this->assertEqualsWithDelta(43.335, $share['protein_g'], 1e-6);
            $this->assertFalse($share['is_snack']);
        }
        $this->assertEqualsWithDelta(321, $shares[5]['calories_kcal'], 1e-6);
        $this->assertTrue($shares[5]['is_snack']);
    }

    public function test_all_main_meal_and_optional_snack_patterns_preserve_every_daily_target(): void
    {
        foreach (range(2, 6) as $main) {
            foreach (range(0, 2) as $snacks) {
                $shares = app(MealPortionBalancer::class)->mealTargets($this->slots($main, $snacks), [], $this->targets());
                foreach ($this->targets() as $field => $value) {
                    $this->assertEqualsWithDelta($value, array_sum(array_column($shares, $field)), 1e-6);
                }
                $this->assertCount($main + $snacks, $shares);
            }
        }
    }

    public function test_fixed_meals_are_subtracted_and_the_residual_is_redistributed_without_mutation(): void
    {
        $new = $this->slots(3, 1);
        array_shift($new);
        $fixed = [['date' => '2026-11-09', 'slot' => 'breakfast', 'snapshot' => ['totals' => [
            'energy_kcal' => 800, 'protein_g' => 60, 'carbohydrate_g' => 100, 'fibre_g' => 10, 'fat_g' => 25,
        ]]]];
        $before = $fixed;
        $shares = app(MealPortionBalancer::class)->mealTargets($new, $fixed, $this->targets());
        $this->assertSame($before, $fixed);
        $this->assertEqualsWithDelta(2410, array_sum(array_column($shares, 'calories_kcal')), 1e-6);
        $this->assertEqualsWithDelta(180.75, array_sum(array_column($shares, 'protein_g')), 1e-6);
        $this->assertEqualsWithDelta(271.125, array_sum(array_column($shares, 'carbohydrate_g')), 1e-6);
    }

    private function syntheticDay(float $firstEnergy, float $firstProtein): array
    {
        $rows = [];
        foreach (['lunch', 'dinner'] as $i => $slot) {
            $rows[] = ['date' => '2026-11-09', 'slot' => $slot, 'meal_type' => $slot, 'snapshot' => [
                'portion_balance' => ['meal_targets' => ['calories_kcal' => $i ? 200 : 800]],
                'totals' => ['energy_kcal' => $i ? 1000 - $firstEnergy : $firstEnergy,
                    'protein_g' => $i ? 50 - $firstProtein : $firstProtein,
                    'carbohydrate_g' => 50, 'fat_g' => 20, 'fibre_g' => 0],
            ]];
        }
        return $rows;
    }

    private function syntheticTarget(): array
    {
        return ['calories_kcal' => 1000, 'protein_g' => 50, 'carbohydrate_g' => 100, 'fat_g' => 40];
    }

    public function test_a_correct_daily_total_cannot_hide_an_oversized_main_meal(): void
    {
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->assertFits($this->syntheticDay(800, 25), [], $this->syntheticTarget());
    }

    public function test_a_correct_daily_total_cannot_hide_protein_concentrated_in_one_main_meal(): void
    {
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->assertFits($this->syntheticDay(500, 45), [], $this->syntheticTarget());
    }

    public function test_confirmation_rederives_shares_instead_of_trusting_snapshot_metadata(): void
    {
        $rows = $this->syntheticDay(500, 25);
        app(MealPortionBalancer::class)->assertFits($rows, [], $this->syntheticTarget());
        $this->assertCount(2, $rows);
    }

    public function test_a_small_snack_can_have_less_protein_when_full_meals_and_day_are_balanced(): void
    {
        $rows = $this->syntheticDay(450, 24);
        $rows[1]['snapshot']['totals']['energy_kcal'] = 450;
        $rows[1]['snapshot']['totals']['protein_g'] = 24;
        $rows[0]['snapshot']['totals']['carbohydrate_g'] = 45;
        $rows[1]['snapshot']['totals']['carbohydrate_g'] = 45;
        $rows[0]['snapshot']['totals']['fat_g'] = 18;
        $rows[1]['snapshot']['totals']['fat_g'] = 18;
        $rows[] = ['date' => '2026-11-09', 'slot' => 'snack_1', 'meal_type' => 'snack', 'snapshot' => ['totals' => [
            'energy_kcal' => 100, 'protein_g' => 2, 'carbohydrate_g' => 10, 'fat_g' => 4, 'fibre_g' => 0,
        ]]];
        app(MealPortionBalancer::class)->assertFits($rows, [], $this->syntheticTarget());
        $this->assertCount(3, $rows);
    }

    public function test_existing_meals_that_use_the_whole_budget_are_not_silently_resized(): void
    {
        $fixed = [['date' => '2026-11-09', 'slot' => 'breakfast', 'snapshot' => ['totals' => [
            'energy_kcal' => 4000, 'protein_g' => 250, 'carbohydrate_g' => 400, 'fat_g' => 100, 'fibre_g' => 0,
        ]]]];
        $this->expectException(ValidationException::class);
        app(MealPortionBalancer::class)->mealTargets($this->slots(2, 0), $fixed, $this->targets());
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/meal-distribution-regression.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertRegressionWeek(int $index): array
    {
        Http::preventStrayRequests();
        $fixture = $this->fixture();
        $draft = $fixture['drafts'][$index];
        // Both examples are tested as empty weeks. The exported week for draft 11
        // now contains later plan entries and is NOT treated as safe to overwrite.
        $before = $draft['generated'];
        $balancer = app(MealPortionBalancer::class);
        $result = app(WeeklyMealSchedule::class)->buildBalanced(
            $draft['start_date'], $draft['slots'], $draft['generated'], [], $fixture['catalog'], $draft['targets'], $balancer
        );
        $this->assertCount(42, $result);
        $this->assertSame($before, $draft['generated']);
        $balancer->assertFits($result, [], $draft['targets']);
        foreach ($result as $row) {
            $meal = $row['snapshot'];
            $matching = array_values(array_filter($draft['generated'][$row['meal_type']], fn ($option) => $option['name'] === $meal['name']));
            $this->assertCount(1, $matching);
            $this->assertSame(array_column($matching[0]['ingredients'], 'ingredient_id'), array_column($meal['ingredients'], 'ingredient_id'));
            $mass = 0;
            $totals = array_fill_keys(['energy_kcal', 'protein_g', 'carbohydrate_g', 'sugars_g', 'fat_g', 'saturates_g', 'fibre_g', 'salt_g'], 0.0);
            foreach ($meal['ingredients'] as $ingredient) {
                $this->assertGreaterThan(0, $ingredient['quantity_g']);
                $this->assertLessThanOrEqual(2, strlen(explode('.', (string) $ingredient['quantity_g'])[1] ?? ''));
                $mass += $ingredient['quantity_g'];
                foreach ($totals as $field => $unused) {
                    $totals[$field] += $fixture['catalog'][$ingredient['ingredient_id']][$field] * $ingredient['quantity_g'] / 100;
                }
            }
            foreach ($totals as $field => $value) {
                $this->assertEqualsWithDelta($value, $meal['totals'][$field], 1e-6);
            }
            $this->assertLessThanOrEqual($row['meal_type'] === 'snack' ? 500 : 1100, $mass);
            $this->assertSame(MealPortionBalancer::VERSION, $meal['portion_balance']['version']);
        }
        return $result;
    }

    public function test_exported_failed_options_can_be_distributed_in_an_empty_week_without_new_foods(): void
    {
        $this->assertRegressionWeek(0);
    }

    public function test_exported_uneven_options_are_distributed_without_oversized_dinners(): void
    {
        $rows = $this->assertRegressionWeek(1);
        foreach ($rows as $row) {
            if ($row['meal_type'] !== 'snack') {
                $this->assertLessThanOrEqual(722.25 + 1e-6, $row['snapshot']['totals']['energy_kcal']);
                $this->assertGreaterThanOrEqual(433.35 - 1e-6, $row['snapshot']['totals']['energy_kcal']);
                $this->assertLessThanOrEqual(56.3355 + 1e-6, $row['snapshot']['totals']['protein_g']);
                $this->assertGreaterThanOrEqual(30.3345 - 1e-6, $row['snapshot']['totals']['protein_g']);
            }
        }
    }

    public function test_fully_occupied_days_are_preserved_without_generating_new_rows(): void
    {
        $fixed = [];
        foreach (range(0, 6) as $day) {
            $date = (new \DateTimeImmutable('2026-11-09'))->modify('+'.$day.' days')->format('Y-m-d');
            foreach (['lunch', 'dinner'] as $slot) {
                $fixed[] = ['date' => $date, 'slot' => $slot, 'snapshot' => ['name' => 'Keep this']];
            }
        }
        $before = $fixed;
        $result = app(WeeklyMealSchedule::class)->buildBalanced('2026-11-09', ['lunch' => 'Lunch', 'dinner' => 'Dinner'], [], $fixed, [], $this->targets(), app(MealPortionBalancer::class));
        $this->assertSame([], $result);
        $this->assertSame($before, $fixed);
    }
}
