<?php

namespace Tests\Unit;

use App\Services\Nutrition\SnapshotNutrition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SnapshotNutritionTest extends TestCase
{
    public static function invalidNumbers(): array
    {
        return array_map(fn ($value) => [$value], [null, '', 'unknown', -1, '-0.1', true, false, [], new \stdClass, INF, NAN, '1e999']);
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_nutrients_stay_unknown(mixed $value): void
    {
        $this->assertNull(SnapshotNutrition::number($value));
        $this->assertSame('—', SnapshotNutrition::format($value));
    }

    public function test_zero_and_numeric_strings_are_known(): void
    {
        foreach ([0, 0.0, '0', '0.00'] as $value) {
            $this->assertSame(0.0, SnapshotNutrition::number($value));
            $this->assertSame('0.0', SnapshotNutrition::format($value));
        }
        $this->assertSame(12.5, SnapshotNutrition::number('12.50'));
    }

    public function test_stored_available_carbohydrate_takes_precedence_including_zero(): void
    {
        foreach ([0, 17] as $available) {
            $totals = SnapshotNutrition::totals(['totals' => [
                'carbohydrate_g' => 60, 'fibre_g' => 10, 'estimated_available_carbohydrate_g' => $available,
            ]]);
            $this->assertSame((float) $available, $totals['estimated_available_carbohydrate_g']);
        }
    }

    public function test_available_carbohydrate_fallback_requires_both_valid_components(): void
    {
        foreach ([['fibre_g' => null], ['fibre_g' => 'bad'], ['fibre_g' => 61], ['carbohydrate_g' => -1]] as $change) {
            $snapshot = ['totals' => array_replace(['carbohydrate_g' => 60, 'fibre_g' => 10], $change)];
            $this->assertNull(SnapshotNutrition::totals($snapshot)['estimated_available_carbohydrate_g']);
        }
        $totals = SnapshotNutrition::totals(['totals' => [
            'carbohydrate_g' => '60', 'fibre_g' => '10', 'estimated_available_carbohydrate_g' => -1,
        ]]);
        $this->assertSame(50.0, $totals['estimated_available_carbohydrate_g']);
    }

    public function test_empty_days_differ_from_meals_with_absent_or_invalid_totals(): void
    {
        $this->assertSame(array_fill_keys(SnapshotNutrition::FIELDS, 0.0), SnapshotNutrition::aggregate([]));
        foreach ([[], ['totals' => null], ['totals' => 'invalid'], ['totals' => false]] as $snapshot) {
            $this->assertSame(array_fill_keys(SnapshotNutrition::FIELDS, null), SnapshotNutrition::aggregate([$snapshot]));
        }
    }

    public function test_mixed_meals_preserve_each_known_nutrient_and_propagate_unknowns(): void
    {
        $totals = SnapshotNutrition::aggregate([
            ['totals' => ['energy_kcal' => 500, 'protein_g' => 30, 'fat_g' => 0, 'carbohydrate_g' => 50, 'fibre_g' => 5]],
            ['totals' => ['energy_kcal' => null, 'protein_g' => '20', 'fat_g' => 0, 'carbohydrate_g' => 40]],
        ]);
        $this->assertNull($totals['energy_kcal']);
        $this->assertSame(50.0, $totals['protein_g']);
        $this->assertSame(0.0, $totals['fat_g']);
        $this->assertSame(90.0, $totals['carbohydrate_g']);
        $this->assertNull($totals['fibre_g']);
        $this->assertNull($totals['estimated_available_carbohydrate_g']);
    }

    public function test_aggregate_overflow_is_unknown_rather_than_infinity(): void
    {
        $snapshot = ['totals' => ['energy_kcal' => PHP_FLOAT_MAX]];
        $this->assertNull(SnapshotNutrition::aggregate([$snapshot, $snapshot])['energy_kcal']);
    }
}
