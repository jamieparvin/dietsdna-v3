<?php

namespace Tests\Unit;

use App\Services\Meals\MealPlanSlots;
use App\Services\Meals\MealTargetAllocator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MealTargetAllocatorTest extends TestCase
{
    private function daily(): array
    {
        return ['calories_kcal' => 2500, 'protein_g' => 150, 'carbohydrate_g' => 300, 'fat_g' => 80];
    }

    public function test_all_patterns_preserve_the_daily_calorie_and_macro_budget(): void
    {
        foreach (range(2, 6) as $meals) {
            foreach ([0, 1, 2] as $snacks) {
                $allocation = (new MealTargetAllocator())->allocate($this->daily(), $meals, $snacks);
                foreach (['energy_kcal' => 'calories_kcal', 'protein_g' => 'protein_g', 'carbohydrate_g' => 'carbohydrate_g', 'fat_g' => 'fat_g'] as $field => $target) {
                    $sum = 0.0;
                    foreach ($allocation as $group) {
                        $sum += $group['targets'][$field] * $group['count'];
                    }
                    $this->assertEqualsWithDelta($this->daily()[$target], $sum, 0.000001, "{$meals} meals / {$snacks} snacks / {$field}");
                }
                $this->assertSame($meals + $snacks, array_sum(array_column($allocation, 'count')));
            }
        }
    }

    public function test_four_five_and_six_meals_do_not_implicitly_create_snacks(): void
    {
        foreach ([4, 5, 6] as $meals) {
            $allocation = (new MealTargetAllocator())->allocate($this->daily(), $meals);
            $this->assertArrayNotHasKey('snack', $allocation);
            $slots = MealPlanSlots::forCount($meals);
            $this->assertCount($meals, $slots);
            foreach ($slots as $slot => $label) {
                $type = MealPlanSlots::typeForSlot($slot);
                $this->assertNotSame('snack', $type);
                $this->assertEqualsWithDelta(2500 / $meals, $allocation[$type]['targets']['energy_kcal'], 0.000001);
            }
        }
    }

    public function test_optional_snacks_reserve_budget_instead_of_adding_calories(): void
    {
        $allocation = (new MealTargetAllocator())->allocate($this->daily(), 5, 1);
        $this->assertSame(1, $allocation['snack']['count']);
        $this->assertEqualsWithDelta(250, $allocation['snack']['targets']['energy_kcal'], 0.000001);
        foreach (['breakfast', 'lunch', 'dinner'] as $type) {
            $this->assertEqualsWithDelta(450, $allocation[$type]['targets']['energy_kcal'], 0.000001);
        }
    }

    public function test_slot_order_matches_the_visible_full_meal_pattern(): void
    {
        $this->assertSame(
            ['breakfast', 'meal_2', 'lunch', 'meal_4', 'meal_5', 'dinner', 'snack_1', 'snack_2'],
            array_keys(MealPlanSlots::forCount(6, 2))
        );
        $slots = array_keys(MealPlanSlots::forCount(6, 2));
        $sorted = $slots;
        usort($sorted, fn ($left, $right) => MealPlanSlots::displayOrder($left) <=> MealPlanSlots::displayOrder($right));
        $this->assertSame($slots, $sorted);
    }

    public function test_invalid_meal_counts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MealTargetAllocator())->allocate($this->daily(), 7);
    }

    public function test_invalid_snack_counts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MealTargetAllocator())->allocate($this->daily(), 5, 3);
    }
}
