<?php

namespace App\Services\Meals;

use Illuminate\Validation\ValidationException;

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

    public static function assertFits(array $meal, ?int $available): void
    {
        if ($available === null) {
            return;
        }
        if (! self::fits($meal, $available)) {
            throw ValidationException::withMessages(['generation' => 'This option does not have an estimated preparation and cooking time within your '.$available.' minutes available. Try different meal ideas, or review this preference if you have more time. Times are AI estimates, not verified limits.']);
        }
    }
}
