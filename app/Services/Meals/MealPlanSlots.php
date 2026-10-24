<?php

namespace App\Services\Meals;

use InvalidArgumentException;

class MealPlanSlots
{
    public static function forCount(
        int $count,
        int $snackCount = 0
    ): array {
        if (! in_array($snackCount, [0, 1, 2], true)) {
            throw new InvalidArgumentException('Unsupported snack count.');
        }

        $slots = match ($count) {
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
                'meal_2' => 'Meal 2',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
            ],
            5 => [
                'breakfast' => 'Breakfast',
                'meal_2' => 'Meal 2',
                'lunch' => 'Lunch',
                'meal_4' => 'Meal 4',
                'dinner' => 'Dinner',
            ],
            6 => [
                'breakfast' => 'Breakfast',
                'meal_2' => 'Meal 2',
                'lunch' => 'Lunch',
                'meal_4' => 'Meal 4',
                'meal_5' => 'Meal 5',
                'dinner' => 'Dinner',
            ],
            default => [],
        };

        if ($slots === []) {
            return [];
        }

        for ($number = 1; $number <= $snackCount; $number++) {
            $slots['snack_'.$number] = 'Snack '.$number;
        }

        return $slots;
    }

    public static function typeForSlot(string $slot): string
    {
        if (str_starts_with($slot, 'snack_')) {
            return 'snack';
        }

        // Additional full meals use ordinary main-meal ideas.
        return match ($slot) {
            'meal_2', 'meal_5' => 'lunch',
            'meal_4' => 'dinner',
            default => $slot,
        };
    }

    public static function displayOrder(string $slot): int
    {
        return match ($slot) {
            'breakfast' => 1,
            'meal_2' => 2,
            'lunch' => 3,
            'meal_4' => 4,
            'meal_5' => 5,
            'dinner' => 6,
            'snack_1' => 7,
            'snack_2' => 8,
            'snack_3' => 9,
            default => 99,
        };
    }
}