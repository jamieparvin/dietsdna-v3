<?php

namespace Tests\Unit;

use App\Services\Meals\MealTargetAllocator;
use PHPUnit\Framework\TestCase;

class MealTargetAllocatorTest extends TestCase
{
    private function daily(): array
    {
        return [
            'calories_kcal' => 3050,
            'protein_g' => 128,
            'carbohydrate_g' => 406,
            'fat_g' => 101.6666667,
        ];
    }

    public function test_five_meals_have_smaller_snacks(): void
    {
        $slots = (new MealTargetAllocator())->allocate($this->daily(), 5);

        $this->assertEquals(610, $slots['breakfast']['targets']['energy_kcal']);
        $this->assertEquals(915, $slots['lunch']['targets']['energy_kcal']);
        $this->assertEquals(915, $slots['dinner']['targets']['energy_kcal']);
        $this->assertEquals(305, $slots['snack']['targets']['energy_kcal']);
        $this->assertSame(2, $slots['snack']['count']);
    }

    public function test_every_pattern_preserves_the_daily_budget(): void
    {
        $daily = $this->daily();

        foreach ([2, 3, 4, 5, 6] as $count) {
            $slots = (new MealTargetAllocator())->allocate($daily, $count);

            $this->assertSame($count, array_sum(array_column($slots, 'count')));

            foreach ([
                'energy_kcal' => 'calories_kcal',
                'protein_g' => 'protein_g',
                'carbohydrate_g' => 'carbohydrate_g',
                'fat_g' => 'fat_g',
            ] as $mealField => $dailyField) {
                $total = 0;

                foreach ($slots as $slot) {
                    $total += $slot['targets'][$mealField] * $slot['count'];
                }

                $this->assertEqualsWithDelta(
                    $daily[$dailyField],
                    $total,
                    0.00001
                );
            }
        }
    }

    public function test_three_meals_have_no_snack_slot(): void
    {
        $slots = (new MealTargetAllocator())->allocate($this->daily(), 3);

        $this->assertArrayNotHasKey('snack', $slots);
    }
}