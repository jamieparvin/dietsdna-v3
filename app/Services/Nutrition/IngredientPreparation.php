<?php

namespace App\Services\Nutrition;

final class IngredientPreparation
{
    public static function normalise(string $preparation): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($preparation)));
    }

    public static function purchaseReady(string $preparation): bool
    {
        // Explicit forms only; food names and preference groups are not evidence
        // that a cooked weight is equivalent to a purchase weight.
        return in_array(self::normalise($preparation), [
            'raw', 'dry', 'ready_to_eat', 'ready-to-eat', 'ready to eat',
            'raw, whole', 'plain, nonfat',
        ], true);
    }
}
