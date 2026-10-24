<?php

namespace Tests\Feature;

use App\Models\NutritionProfile;
use App\Services\Nutrition\NutritionCalculatorSettings;
use App\Services\Nutrition\NutritionTargetCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class NutritionTargetCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-24 12:00:00', 'Europe/London'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function profile(array $overrides = []): NutritionProfile
    {
        return new NutritionProfile(array_replace([
            'date_of_birth' => '1996-10-24', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate',
            'goal' => 'maintain_weight', 'planning_context' => 'general_adult',
            'resistance_training' => false, 'macro_mode' => 'goal',
        ], $overrides));
    }

    private function calculator(array $overrides = []): NutritionTargetCalculator
    {
        return new NutritionTargetCalculator(['revision' => 7,
            'values' => array_replace(NutritionCalculatorSettings::defaults(), $overrides)]);
    }

    public function test_maintenance_retains_the_original_age_and_energy_equation(): void
    {
        $r = $this->calculator()->calculate($this->profile());
        $this->assertSame('ready', $r['status']);
        $this->assertSame(30, $r['age']);
        $this->assertEqualsWithDelta(1780, $r['resting_kcal'], 0.000001);
        $this->assertEqualsWithDelta(2759, $r['maintenance_kcal'], 0.000001);
        $this->assertSame(2760, $r['calories_kcal']);
    }

    public function test_goal_calorie_adjustments_are_unchanged(): void
    {
        foreach (['eat_better' => 2760, 'maintain_weight' => 2760, 'lose_weight' => 2350,
            'gain_weight' => 3060, 'build_muscle' => 2960] as $goal => $expected) {
            $this->assertSame($expected, $this->calculator()->calculate($this->profile(['goal' => $goal]))['calories_kcal']);
        }
    }

    public function test_female_coefficient_is_unchanged(): void
    {
        $r = $this->calculator()->calculate($this->profile(['calculation_sex' => 'female']));
        $this->assertEqualsWithDelta(1614, $r['resting_kcal'], 0.000001);
        $this->assertSame(2500, $r['calories_kcal']);
    }

    public function test_each_goal_uses_its_own_percentage_preset_and_preserves_energy(): void
    {
        $expected = ['eat_better' => [20, 50, 30], 'lose_weight' => [35, 35, 30],
            'maintain_weight' => [30, 40, 30], 'gain_weight' => [20, 50, 30], 'build_muscle' => [30, 45, 25]];
        foreach ($expected as $goal => $split) {
            $r = $this->calculator()->calculate($this->profile(['goal' => $goal]));
            $this->assertSame('goal', $r['macro_source']);
            $this->assertEquals($split, array_values($r['macro_percentages']));
            $this->assertEqualsWithDelta($r['calories_kcal'], 4 * $r['protein_g'] + 4 * $r['carbohydrate_g'] + 9 * $r['fat_g'], 0.000001);
        }
    }

    public function test_muscle_gain_at_the_shown_3050_calories_uses_reference_percentages(): void
    {
        $r = $this->calculator()->calculate($this->profile(['date_of_birth' => '2008-08-03', 'goal' => 'build_muscle']));
        $this->assertSame(3050, $r['calories_kcal']);
        $this->assertEqualsWithDelta(228.75, $r['protein_g'], 0.000001);
        $this->assertEqualsWithDelta(343.125, $r['carbohydrate_g'], 0.000001);
        $this->assertEqualsWithDelta(84.722222, $r['fat_g'], 0.000001);
    }

    public function test_custom_split_changes_macros_without_changing_calories(): void
    {
        $base = $this->calculator()->calculate($this->profile());
        $p = $this->profile(['macro_mode' => 'custom', 'macro_goal' => 'maintain_weight',
            'macro_protein_percent' => 25, 'macro_carbohydrate_percent' => 45, 'macro_fat_percent' => 30]);
        $r = $this->calculator()->calculate($p);
        $this->assertSame($base['calories_kcal'], $r['calories_kcal']);
        $this->assertSame('custom', $r['macro_source']);
        $this->assertEqualsWithDelta(172.5, $r['protein_g'], 0.000001);
        $this->assertEqualsWithDelta(310.5, $r['carbohydrate_g'], 0.000001);
        $this->assertEqualsWithDelta(92, $r['fat_g'], 0.000001);
    }

    public function test_changed_goal_requires_review_of_a_custom_split(): void
    {
        $p = $this->profile(['macro_mode' => 'custom', 'macro_goal' => 'build_muscle',
            'macro_protein_percent' => 30, 'macro_carbohydrate_percent' => 45, 'macro_fat_percent' => 25]);
        $this->assertSame('review', $this->calculator()->calculate($p)['status']);
        $this->assertSame('ready', $this->calculator()->calculateUsingGoalDefaults($p)['status']);
        $this->assertSame('custom', $p->macro_mode); // Preview calculation cannot mutate the saved profile.
    }

    public function test_invalid_custom_percentages_produce_no_targets(): void
    {
        $r = $this->calculator()->calculate($this->profile(['macro_mode' => 'custom', 'macro_goal' => 'maintain_weight',
            'macro_protein_percent' => 30, 'macro_carbohydrate_percent' => 50, 'macro_fat_percent' => 25]));
        $this->assertSame('review', $r['status']);
        $this->assertArrayNotHasKey('calories_kcal', $r);
    }

    public function test_admin_percentages_are_applied(): void
    {
        $r = $this->calculator(['macro_maintain_weight_protein_percent' => 25,
            'macro_maintain_weight_carbohydrate_percent' => 45, 'macro_maintain_weight_fat_percent' => 30])
            ->calculate($this->profile());
        $this->assertEqualsWithDelta(172.5, $r['protein_g'], 0.000001);
    }

    public function test_invalid_admin_split_produces_no_targets(): void
    {
        $this->assertSame('review', $this->calculator(['macro_build_muscle_protein_percent' => 35])
            ->calculate($this->profile(['goal' => 'build_muscle']))['status']);
    }

    public function test_custom_calorie_and_activity_settings_still_work(): void
    {
        $r = $this->calculator(['activity_moderate' => 1.6, 'gain_surplus' => 350])
            ->calculate($this->profile(['goal' => 'gain_weight']));
        $this->assertSame(3200, $r['calories_kcal']);
    }

    public function test_missing_information_produces_no_target(): void
    {
        $this->assertSame('incomplete', $this->calculator()->calculate(null)['status']);
        $this->assertSame('incomplete', $this->calculator()->calculate($this->profile(['weight_kg' => null]))['status']);
    }

    public function test_individual_review_remains_in_force(): void
    {
        $this->assertSame('review', $this->calculator()->calculate($this->profile(['planning_context' => 'needs_review']))['status']);
    }

    public function test_weight_loss_rejects_a_higher_target_weight(): void
    {
        $this->assertSame('review', $this->calculator()->calculate($this->profile(['goal' => 'lose_weight', 'target_weight_kg' => 85]))['status']);
    }

    public function test_low_calorie_estimate_still_requires_review(): void
    {
        $p = $this->profile(['weight_kg' => 50, 'height_cm' => 160, 'calculation_sex' => 'female',
            'activity_level' => 'sedentary', 'goal' => 'lose_weight']);
        $this->assertSame('review', $this->calculator()->calculate($p)['status']);
    }
}
