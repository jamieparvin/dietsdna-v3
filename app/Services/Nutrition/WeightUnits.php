<?php

namespace App\Services\Nutrition;

class WeightUnits
{
    public const KG_PER_LB = 0.45359237;

    public const MIN_KG = 20;

    public const MAX_KG = 500;

    public static function pounds(mixed $kilograms): ?float
    {
        return is_numeric($kilograms) && is_finite((float) $kilograms) ? (float) $kilograms / self::KG_PER_LB : null;
    }

    public static function inputLimit(float $kilograms): string
    {
        return json_encode(self::pounds($kilograms), JSON_THROW_ON_ERROR);
    }

    public static function kilograms(float $pounds): float
    {
        return $pounds * self::KG_PER_LB;
    }

    public static function format(mixed $kilograms): string
    {
        $pounds = self::pounds($kilograms);

        return $pounds === null ? 'Not set' : number_format($pounds, 1);
    }

    public static function input(mixed $kilograms): string
    {
        $pounds = self::pounds($kilograms);

        return $pounds === null ? '' : number_format($pounds, 1, '.', '');
    }

    public static function oldInput(string $lbsField, string $kgField, mixed $stored): string
    {
        if (array_key_exists($lbsField, session()->getOldInput())) {
            $value = old($lbsField);

            return is_scalar($value) ? (string) $value : '';
        }

        return self::input(array_key_exists($kgField, session()->getOldInput()) ? old($kgField) : $stored);
    }
}
