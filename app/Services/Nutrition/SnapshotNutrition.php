<?php

namespace App\Services\Nutrition;

/** Read-only nutrition for historical snapshots; never repairs stored data. */
final class SnapshotNutrition
{
    public const FIELDS = ['energy_kcal', 'protein_g', 'carbohydrate_g', 'sugars_g',
        'fat_g', 'saturates_g', 'fibre_g', 'salt_g', 'estimated_available_carbohydrate_g'];

    public static function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0
            ? (float) $value : null;
    }

    public static function totals(mixed $snapshot): array
    {
        $raw = is_array($snapshot) && is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
        $totals = [];
        foreach (self::FIELDS as $field) {
            $totals[$field] = self::number($raw[$field] ?? null);
        }

        if ($totals['estimated_available_carbohydrate_g'] === null) {
            $carbs = $totals['carbohydrate_g'];
            $fibre = $totals['fibre_g'];
            if ($carbs !== null && $fibre !== null && $fibre <= $carbs) {
                $totals['estimated_available_carbohydrate_g'] = $carbs - $fibre;
            }
        }

        return $totals;
    }

    /** Pass actual meal snapshots only; an empty iterable is an empty day. */
    public static function aggregate(iterable $snapshots): array
    {
        $totals = array_fill_keys(self::FIELDS, 0.0);
        foreach ($snapshots as $snapshot) {
            $nutrition = self::totals($snapshot);
            foreach ($totals as $field => $value) {
                $totals[$field] = $value === null || $nutrition[$field] === null
                    ? null : self::number($value + $nutrition[$field]);
            }
        }

        return $totals;
    }

    public static function format(mixed $value, int $decimals = 1): string
    {
        $number = self::number($value);

        return $number === null ? '—' : number_format($number, $decimals);
    }
}
