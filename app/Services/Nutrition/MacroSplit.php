<?php

namespace App\Services\Nutrition;

class MacroSplit
{
    // Editable product boundaries for this version, not universal prescriptions.
    public const LIMITS = [
        'protein' => [10, 35],
        'carbohydrate' => [5, 70],
        'fat' => [20, 75],
    ];

    public static function valid(array $split): bool
    {
        $hundredths = 0;
        foreach (self::LIMITS as $name => [$min, $max]) {
            $value = $split[$name] ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                return false;
            }
            $value = (float) $value;
            if ($value < $min || $value > $max || abs($value * 100 - round($value * 100)) > 0.000001) {
                return false;
            }
            $hundredths += (int) round($value * 100);
        }
        return $hundredths === 10000;
    }

    public static function grams(int $calories, array $split): array
    {
        if ($calories <= 0 || ! self::valid($split)) {
            throw new \InvalidArgumentException('A valid calorie target and 100% macro split are required.');
        }
        return [
            'protein_g' => $calories * (float) $split['protein'] / 100 / 4,
            'carbohydrate_g' => $calories * (float) $split['carbohydrate'] / 100 / 4,
            'fat_g' => $calories * (float) $split['fat'] / 100 / 9,
        ];
    }
}
