<?php

namespace App\Services\Meals;

use InvalidArgumentException;

class MealTargetAllocator
{
    public const VERSION = 'meal-allocation-v1';

    public function allocate(array $daily, int $mealCount): array
    {
        $patterns = [
            2 => [
                'lunch' => [45, 1],
                'dinner' => [55, 1],
            ],
            3 => [
                'breakfast' => [25, 1],
                'lunch' => [35, 1],
                'dinner' => [40, 1],
            ],
            4 => [
                'breakfast' => [25, 1],
                'lunch' => [30, 1],
                'dinner' => [35, 1],
                'snack' => [10, 1],
            ],
            5 => [
                'breakfast' => [20, 1],
                'lunch' => [30, 1],
                'dinner' => [30, 1],
                'snack' => [10, 2],
            ],
            6 => [
                'breakfast' => [20, 1],
                'lunch' => [25, 1],
                'dinner' => [25, 1],
                'snack' => [10, 3],
            ],
        ];

        if (! isset($patterns[$mealCount])) {
            throw new InvalidArgumentException('Unsupported meals per day.');
        }

        $result = [];

        foreach ($patterns[$mealCount] as $type => [$percent, $count]) {
            $fraction = $percent / 100;

            $result[$type] = [
                'label' => ucfirst($type),
                'percent' => $percent,
                'count' => $count,
                'targets' => [
                    'energy_kcal' => $daily['calories_kcal'] * $fraction,
                    'protein_g' => $daily['protein_g'] * $fraction,
                    'carbohydrate_g' => $daily['carbohydrate_g'] * $fraction,
                    'fat_g' => $daily['fat_g'] * $fraction,
                ],
            ];
        }

        return $result;
    }
}