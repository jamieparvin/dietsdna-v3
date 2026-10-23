<?php

namespace Tests\Feature;

use App\Models\NutritionProfile;
use App\Services\Nutrition\NutritionTargetCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class NutritionTargetCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-10-23 12:00:00', 'Europe/London')
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function calculate(array $changes = []): array
    {
        $profile = new NutritionProfile(array_replace([
            'date_of_birth' => '1996-10-23',
            'height_cm' => 180,
            'weight_kg' => 80,
            'calculation_sex' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'maintain_weight',
            'planning_context' => 'general_adult',
            'resistance_training' => true,
            'target_weight_kg' => null,
        ], $changes));

        return app(NutritionTargetCalculator::class)->calculate($profile);
    }

    public function test_maintenance_uses_expected_age_and_energy(): void
    {
        $result = $this->calculate();

        $this->assertSame('ready', $result['status']);
        $this->assertSame(30, $result['age']);
        $this->assertEqualsWithDelta(1780, $result['resting_kcal'], 0.001);
        $this->assertEqualsWithDelta(2759, $result['maintenance_kcal'], 0.001);
        $this->assertSame(2760, $result['calories_kcal']);
    }

    public function test_each_goal_has_the_expected_calorie_target(): void
    {
        $expected = [
            'eat_better' => 2760,
            'maintain_weight' => 2760,
            'lose_weight' => 2350,
            'gain_weight' => 3060,
            'build_muscle' => 2960,
        ];

        foreach ($expected as $goal => $calories) {
            $result = $this->calculate(['goal' => $goal]);

            $this->assertSame('ready', $result['status'], $goal);
            $this->assertSame($calories, $result['calories_kcal'], $goal);
        }
    }

    public function test_female_equation_uses_the_correct_coefficient(): void
    {
        $result = $this->calculate(['calculation_sex' => 'female']);

        $this->assertSame('ready', $result['status']);
        $this->assertEqualsWithDelta(1614, $result['resting_kcal'], 0.001);
        $this->assertSame(2500, $result['calories_kcal']);
    }

    public function test_protein_depends_on_goal_and_training(): void
    {
        $cases = [
            ['maintain_weight', true, 128],
            ['maintain_weight', false, 96],
            ['lose_weight', true, 160],
            ['lose_weight', false, 128],
        ];

        foreach ($cases as [$goal, $training, $protein]) {
            $result = $this->calculate([
                'goal' => $goal,
                'resistance_training' => $training,
            ]);

            $this->assertSame('ready', $result['status']);
            $this->assertEqualsWithDelta($protein, $result['protein_g'], 0.001);
        }
    }

    public function test_macro_energy_matches_the_calorie_budget(): void
    {
        $result = $this->calculate();

        $macroCalories = $result['protein_g'] * 4
            + $result['fat_g'] * 9
            + $result['carbohydrate_g'] * 4;

        $this->assertEqualsWithDelta(
            $result['calories_kcal'],
            $macroCalories,
            0.001
        );

        $this->assertEqualsWithDelta(
            $result['calories_kcal'] * 0.30,
            $result['fat_g'] * 9,
            0.001
        );
    }

    public function test_missing_information_produces_no_target(): void
    {
        $result = $this->calculate(['date_of_birth' => null]);

        $this->assertSame('incomplete', $result['status']);
        $this->assertArrayNotHasKey('calories_kcal', $result);
    }

    public function test_individual_review_produces_no_target(): void
    {
        $result = $this->calculate(['planning_context' => 'needs_review']);

        $this->assertSame('review', $result['status']);
        $this->assertArrayNotHasKey('calories_kcal', $result);
    }

    public function test_weight_loss_rejects_a_higher_target_weight(): void
    {
        $result = $this->calculate([
            'goal' => 'lose_weight',
            'target_weight_kg' => 90,
        ]);

        $this->assertSame('review', $result['status']);
        $this->assertArrayNotHasKey('calories_kcal', $result);
    }

    public function test_low_calorie_estimate_requires_review(): void
    {
        $result = $this->calculate([
            'date_of_birth' => '1966-10-23',
            'calculation_sex' => 'female',
            'height_cm' => 160,
            'weight_kg' => 55,
            'activity_level' => 'sedentary',
            'goal' => 'lose_weight',
        ]);

        $this->assertSame('review', $result['status']);
        $this->assertArrayNotHasKey('calories_kcal', $result);
    }
}