<?php

namespace App\Services\Meals;

use InvalidArgumentException;

class MealTargetAllocator
{
    public const VERSION = 'meal-allocation-v2';

    private const SNACK_PERCENT = 10;

    public function allocate(
        array $daily,
        int $mealCount,
        int $snackCount = 0
    ): array {
        if (! in_array($mealCount, [2, 3, 4, 5, 6], true)) {
            throw new InvalidArgumentException('Unsupported meals per day.');
        }

        if (! in_array($snackCount, [0, 1, 2], true)) {
            throw new InvalidArgumentException('Unsupported snack count.');
        }

        $slots = MealPlanSlots::forCount($mealCount, $snackCount);

        $mealPercent = (
            100 - $snackCount * self::SNACK_PERCENT
        ) / $mealCount;

        $labels = [
            'breakfast' => 'Breakfast',
            'lunch' => 'Lunch / additional meal',
            'dinner' => 'Dinner / additional meal',
            'snack' => 'Optional snack',
        ];

        $result = [];

        foreach ($slots as $slot => $slotLabel) {
            $type = MealPlanSlots::typeForSlot($slot);

            if (isset($result[$type])) {
                $result[$type]['count']++;

                continue;
            }

            $percent = $type === 'snack'
                ? self::SNACK_PERCENT
                : $mealPercent;

            $fraction = $percent / 100;

            $result[$type] = [
                'label' => $labels[$type],
                'percent' => $percent,
                'count' => 1,
                'targets' => [
                    'energy_kcal' =>
                        $daily['calories_kcal'] * $fraction,

                    'protein_g' =>
                        $daily['protein_g'] * $fraction,

                    'carbohydrate_g' =>
                        $daily['carbohydrate_g'] * $fraction,

                    'fat_g' =>
                        $daily['fat_g'] * $fraction,
                ],
            ];
        }

        return $result;
    }
}