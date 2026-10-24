<?php

namespace Tests\Unit;

use App\Services\Meals\MealPlanSlots;
use App\Services\Meals\WeeklyMealSchedule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WeeklyMealScheduleTest extends TestCase
{
    private function pools(): array
    {
        $pools = [];
        foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $type) {
            for ($i = 0; $i < 3; $i++) {
                $pools[$type][] = ['name' => $type.'-'.$i, 'ingredients' => [['quantity_g' => 73.25]]];
            }
        }
        return $pools;
    }

    public function test_every_meal_pattern_creates_seven_days_of_unique_slots(): void
    {
        foreach ([2, 3, 4, 5, 6] as $count) {
            $rows = (new WeeklyMealSchedule())->build('2026-10-26', MealPlanSlots::forCount($count), $this->pools(), []);
            $keys = array_map(fn ($row) => $row['date'].'|'.$row['slot'], $rows);
            $this->assertCount($count * 7, $rows);
            $this->assertCount($count * 7, array_unique($keys));
            $this->assertSame('2026-10-26', $rows[0]['date']);
            $this->assertSame('2026-11-01', $rows[array_key_last($rows)]['date']);
        }
    }

    public function test_snacks_on_the_same_day_use_different_options(): void
    {
        $rows = (new WeeklyMealSchedule())->build('2026-10-26', MealPlanSlots::forCount(6), $this->pools(), []);
        $snacks = array_filter($rows, fn ($row) => $row['date'] === '2026-10-26' && $row['meal_type'] === 'snack');
        $this->assertCount(3, array_unique(array_map(fn ($row) => $row['snapshot']['name'], $snacks)));
    }

    public function test_occupied_slots_are_omitted_and_quantities_are_unchanged(): void
    {
        $rows = (new WeeklyMealSchedule())->build('2026-10-26', MealPlanSlots::forCount(5), $this->pools(), ['2026-10-26|lunch']);
        $this->assertCount(34, $rows);
        $this->assertNotContains('2026-10-26|lunch', array_map(fn ($row) => $row['date'].'|'.$row['slot'], $rows));
        foreach ($rows as $row) {
            $this->assertSame(73.25, $row['snapshot']['ingredients'][0]['quantity_g']);
        }
    }

    public function test_missing_options_fail_instead_of_creating_a_partial_schedule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WeeklyMealSchedule())->build('2026-10-26', MealPlanSlots::forCount(5), [], []);
    }
}
