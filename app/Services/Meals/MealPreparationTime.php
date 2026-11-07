<?php

namespace App\Services\Meals;

class MealPreparationTime
{
    public const VERSION = 'prep-and-cook-estimate-v1';

    public const OPTIONS = [10, 15, 20, 30];

    public static function allowed(?int $saved): array
    {
        return in_array($saved, [45, 60], true) ? [...self::OPTIONS, $saved] : self::OPTIONS;
    }

    public static function total(array $meal): ?int
    {
        foreach (['estimated_prep_minutes', 'estimated_cook_minutes'] as $field) {
            if (! isset($meal[$field]) || filter_var($meal[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1440]]) === false) {
                return null;
            }
        }
        $total = (int) $meal['estimated_prep_minutes'] + (int) $meal['estimated_cook_minutes'];

        return $total;
    }

    public static function fits(array $meal, ?int $available): bool
    {
        return $available === null || (self::total($meal) !== null && self::total($meal) <= $available);
    }

    /** Lower scores prefer known times within the preference, then the closest estimates. */
    public static function score(array $meal, ?int $available): array
    {
        if ($available === null) {
            return [0, 0, 0];
        }
        $minutes = self::total($meal);

        return [$minutes === null || $minutes > $available ? 1 : 0, $minutes === null ? 1 : 0, $minutes === null ? 0 : max(0, $minutes - $available)];
    }

    public static function scoreRows(array $rows, ?int $available): array
    {
        $score = [0, 0, 0];
        foreach ($rows as $row) {
            foreach (self::score($row['snapshot'], $available) as $index => $value) {
                $score[$index] += $value;
            }
        }

        return $score;
    }
}
