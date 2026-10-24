<?php

namespace App\Services\Meals;

class MealPlanSlots
{
    public static function forCount(int $count): array
    {
        return match ($count) {
            2 => [
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
            ],
            3 => [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
            ],
            4 => [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
                'snack_1' => 'Snack 1',
            ],
            5 => [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
                'snack_1' => 'Snack 1',
                'snack_2' => 'Snack 2',
            ],
            6 => [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
                'snack_1' => 'Snack 1',
                'snack_2' => 'Snack 2',
                'snack_3' => 'Snack 3',
            ],
            default => [],
        };
    }
}