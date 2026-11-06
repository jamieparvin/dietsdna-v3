<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;
use App\Models\User;
use Carbon\CarbonImmutable;

class BodyMassIndex
{
    public function calculate(mixed $kilograms, mixed $heightCm): ?float
    {
        if (! is_numeric($kilograms) || ! is_numeric($heightCm) || ! is_finite((float) $kilograms) || ! is_finite((float) $heightCm) || (float) $kilograms < WeightUnits::MIN_KG || (float) $kilograms > WeightUnits::MAX_KG || (float) $heightCm < 50 || (float) $heightCm > 300) {
            return null;
        }
        $squareMetres = ((float) $heightCm / 100) ** 2;
        $bmi = $squareMetres > 0 && is_finite($squareMetres) ? (float) $kilograms / $squareMetres : null;

        return $bmi !== null && is_finite($bmi) && $bmi > 0 ? $bmi : null;
    }

    /** @return array{value: ?float, weight_kg: mixed, height_cm: mixed, source: string, date: ?string} */
    public function forProfile(?NutritionProfile $profile): array
    {
        return [
            'value' => $this->calculate($profile?->weight_kg, $profile?->height_cm),
            'weight_kg' => $profile?->weight_kg, 'height_cm' => $profile?->height_cm,
            'source' => 'profile', 'date' => $profile?->updated_at?->toDateString(),
        ];
    }

    /** @return array{value: ?float, weight_kg: mixed, height_cm: mixed, source: string, date: ?string} */
    public function forProgress(User $user): array
    {
        $profile = $user->nutritionProfile()->first();
        $entry = $user->weightEntries()->where('recorded_on', '<', CarbonImmutable::now('Europe/London')->startOfDay()->addDay()->toDateString())
            ->orderByDesc('recorded_on')->orderByDesc('id')->first();
        if (! $entry) {
            return $this->forProfile($profile);
        }

        return [
            'value' => $this->calculate($entry->weight_kg, $profile?->height_cm),
            'weight_kg' => $entry->weight_kg, 'height_cm' => $profile?->height_cm,
            'source' => 'entry', 'date' => $entry->recorded_on->toDateString(),
        ];
    }
}
