<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;
use Carbon\CarbonImmutable;

class NutritionTargetCalculator
{
    public const VERSION = 'dietsdna-targets-v1';

    // Product assumptions, not measured activity expenditure.
    private const ACTIVITY_FACTORS = [
        'sedentary' => 1.20,
        'light' => 1.375,
        'moderate' => 1.55,
        'high' => 1.725,
    ];

    public function calculate(?NutritionProfile $profile): array
    {
        if (! $profile) {
            return $this->unavailable(
                'incomplete',
                'Save your measurements and calculation details to see an estimate.'
            );
        }

        $required = [
            'date_of_birth' => 'date of birth',
            'height_cm' => 'height',
            'weight_kg' => 'weight',
            'calculation_sex' => 'sex used for calculation',
            'activity_level' => 'activity level',
            'goal' => 'goal',
            'planning_context' => 'planning suitability',
            'resistance_training' => 'resistance training preference',
        ];

        $missing = [];

        foreach ($required as $field => $label) {
            if ($profile->{$field} === null || $profile->{$field} === '') {
                $missing[] = $label;
            }
        }

        if ($missing) {
            return $this->unavailable(
                'incomplete',
                'Complete: '.implode(', ', $missing).'.'
            );
        }

        if ($profile->planning_context !== 'general_adult') {
            return $this->unavailable(
                'review',
                'Your circumstances need an individual nutrition review before '
                .'automatic calorie and macro targets are provided.'
            );
        }

        $today = CarbonImmutable::now('Europe/London')->startOfDay();
        $birthDate = CarbonImmutable::parse(
            $profile->date_of_birth->format('Y-m-d'),
            'Europe/London'
        );

        if ($birthDate->isAfter($today)) {
            return $this->unavailable('review', 'Check your date of birth.');
        }

        $age = (int) $birthDate->diffInYears($today);
        $weight = (float) $profile->weight_kg;
        $height = (float) $profile->height_cm;

        if (
            $age < 18 || $age > 120
            || $weight < 20 || $weight > 500
            || $height < 50 || $height > 300
        ) {
            return $this->unavailable(
                'review',
                'Check your measurements. Automatic planning is for adults.'
            );
        }

        if (
            ! in_array($profile->calculation_sex, ['male', 'female'], true)
            || ! isset(self::ACTIVITY_FACTORS[$profile->activity_level])
            || ! in_array($profile->goal, [
                'eat_better',
                'lose_weight',
                'maintain_weight',
                'gain_weight',
                'build_muscle',
            ], true)
        ) {
            return $this->unavailable(
                'incomplete',
                'Choose valid calculation details, activity and goal.'
            );
        }

        $bmi = $weight / (($height / 100) ** 2);

        // Conservative scope for this first version.
        // These are product review gates, not diagnostic rules.
        if ($bmi < 18.5 || $bmi >= 30) {
            return $this->unavailable(
                'review',
                'Your measurements fall outside this first version’s automatic '
                .'planning range. An individual review is needed to choose '
                .'appropriate energy and protein targets.'
            );
        }

        if ($profile->target_weight_kg !== null) {
            $targetWeight = (float) $profile->target_weight_kg;
            $targetBmi = $targetWeight / (($height / 100) ** 2);

            if ($profile->goal === 'lose_weight') {
                if ($targetWeight >= $weight || $targetBmi < 18.5) {
                    return $this->unavailable(
                        'review',
                        'Check your target weight: it must be below your current '
                        .'weight without entering the underweight range.'
                    );
                }
            }

            if (
                $profile->goal === 'gain_weight'
                && $targetWeight <= $weight
            ) {
                return $this->unavailable(
                    'review',
                    'For weight gain, choose a target above your current weight.'
                );
            }
        }

        // Common rounded Mifflin–St Jeor equation.
        $sexConstant = $profile->calculation_sex === 'male' ? 5 : -161;

        $resting = 10 * $weight
            + 6.25 * $height
            - 5 * $age
            + $sexConstant;

        $factor = self::ACTIVITY_FACTORS[$profile->activity_level];
        $maintenance = $resting * $factor;

        $adjusted = match ($profile->goal) {
            'lose_weight' => $maintenance * 0.85,
            'gain_weight' => $maintenance + 300,
            'build_muscle' => $maintenance + 200,
            default => $maintenance,
        };

        $calories = (int) (round($adjusted / 10) * 10);

        // Conservative product limits. Do not silently clamp the result.
        if ($calories < 1500 || $calories > 4500) {
            return $this->unavailable(
                'review',
                'The estimate is outside this version’s automatic planning range '
                .'of 1,500–4,500 kcal. An individual review is needed.'
            );
        }

        $trains = (bool) $profile->resistance_training;

        // Starting protein defaults in grams per kilogram of current weight.
        $proteinFactor = match (true) {
            $trains && $profile->goal === 'lose_weight' => 2.0,
            $trains || $profile->goal === 'build_muscle' => 1.6,
            $profile->goal === 'lose_weight' => 1.6,
            default => 1.2,
        };

        $protein = $weight * $proteinFactor;
        $fat = ($calories * 0.30) / 9;
        $carbohydrate = ($calories - $protein * 4 - $fat * 9) / 4;

        if ($carbohydrate <= 0) {
            return $this->unavailable(
                'review',
                'The starting macro allocation needs an individual review.'
            );
        }

        return [
            'status' => 'ready',
            'message' => 'Starting estimates based on your saved profile.',
            'version' => self::VERSION,
            'age' => $age,
            'resting_kcal' => $resting,
            'activity_factor' => $factor,
            'maintenance_kcal' => $maintenance,
            'adjustment_kcal' => $calories - $maintenance,
            'calories_kcal' => $calories,
            'protein_g' => $protein,
            'fat_g' => $fat,
            'carbohydrate_g' => $carbohydrate,
            'protein_factor' => $proteinFactor,
        ];
    }

    private function unavailable(string $status, string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'version' => self::VERSION,
        ];
    }
}