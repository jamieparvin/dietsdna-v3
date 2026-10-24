<?php

namespace Tests\Unit;

use App\Services\Nutrition\MacroSplit;
use PHPUnit\Framework\TestCase;

class MacroSplitTest extends TestCase
{
    public function test_reference_muscle_split_converts_calories_to_grams(): void
    {
        $grams = MacroSplit::grams(3050, ['protein' => 30, 'carbohydrate' => 45, 'fat' => 25]);
        $this->assertEqualsWithDelta(228.75, $grams['protein_g'], 0.000001);
        $this->assertEqualsWithDelta(343.125, $grams['carbohydrate_g'], 0.000001);
        $this->assertEqualsWithDelta(84.722222, $grams['fat_g'], 0.000001);
        $this->assertEqualsWithDelta(3050, 4 * $grams['protein_g'] + 4 * $grams['carbohydrate_g'] + 9 * $grams['fat_g'], 0.000001);
    }

    public function test_hundredths_total_exactly_one_hundred(): void
    {
        $this->assertTrue(MacroSplit::valid(['protein' => '33.33', 'carbohydrate' => '33.33', 'fat' => '33.34']));
        $this->assertFalse(MacroSplit::valid(['protein' => 33.33, 'carbohydrate' => 33.33, 'fat' => 33.33]));
    }

    public function test_missing_out_of_range_and_non_finite_values_are_rejected(): void
    {
        foreach ([[], ['protein' => 40, 'carbohydrate' => 35, 'fat' => 25],
            ['protein' => NAN, 'carbohydrate' => 45, 'fat' => 25],
            ['protein' => INF, 'carbohydrate' => 45, 'fat' => 25],
            ['protein' => 30.001, 'carbohydrate' => 44.999, 'fat' => 25]] as $split) {
            $this->assertFalse(MacroSplit::valid($split));
        }
    }

    public function test_invalid_totals_cannot_be_converted_into_targets(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MacroSplit::grams(3050, ['protein' => 30, 'carbohydrate' => 50, 'fat' => 25]);
    }
}
