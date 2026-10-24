<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;
use Carbon\CarbonImmutable;

class NutritionTargetCalculator
{
    public const VERSION = 'dietsdna-targets-v3-goal-macros';

    public function __construct(private readonly ?array $configuration = null)
    {
    }

    public function calculateUsingGoalDefaults(?NutritionProfile $profile): array
    {
        if (! $profile) {
            return $this->calculate(null);
        }
        $copy = clone $profile;
        $copy->macro_mode = 'goal';
        return $this->calculate($copy);
    }

    public function calculate(?NutritionProfile $profile): array
    {
        if (! $profile) {
            return $this->unavailable('incomplete', 'Save your measurements and calculation details to see an estimate.');
        }
        $required = [
            'date_of_birth' => 'date of birth', 'height_cm' => 'height', 'weight_kg' => 'weight',
            'calculation_sex' => 'sex used for calculation', 'activity_level' => 'activity level',
            'goal' => 'goal', 'planning_context' => 'planning suitability',
            'resistance_training' => 'resistance training preference',
        ];
        $missing = [];
        foreach ($required as $field => $label) {
            if ($profile->{$field} === null || $profile->{$field} === '') {
                $missing[] = $label;
            }
        }
        if ($missing) {
            return $this->unavailable('incomplete', 'Complete: '.implode(', ', $missing).'.');
        }
        if ($profile->planning_context !== 'general_adult') {
            return $this->unavailable('review', 'Your circumstances need an individual nutrition review before automatic calorie and macro targets are provided.');
        }
        $today = CarbonImmutable::now('Europe/London')->startOfDay();
        $birthDate = CarbonImmutable::parse($profile->date_of_birth->format('Y-m-d'), 'Europe/London');
        if ($birthDate->isAfter($today)) {
            return $this->unavailable('review', 'Check your date of birth.');
        }
        $age = (int) $birthDate->diffInYears($today);
        $weight = (float) $profile->weight_kg;
        $height = (float) $profile->height_cm;
        if ($age < 18 || $age > 120 || $weight < 20 || $weight > 500 || $height < 50 || $height > 300) {
            return $this->unavailable('review', 'Check your measurements. Automatic planning is for adults.');
        }
        $configuration = $this->configuration ?? app(NutritionCalculatorSettings::class)->current();
        $settings = array_replace(NutritionCalculatorSettings::defaults(), $configuration['values']);
        $activityFactors = [
            'sedentary' => $settings['activity_sedentary'], 'light' => $settings['activity_light'],
            'moderate' => $settings['activity_moderate'], 'high' => $settings['activity_high'],
        ];
        if (! in_array($profile->calculation_sex, ['male', 'female'], true)
            || ! isset($activityFactors[$profile->activity_level])
            || ! array_key_exists($profile->goal, NutritionCalculatorSettings::macroGoals())) {
            return $this->unavailable('incomplete', 'Choose valid calculation details, activity and goal.');
        }
        $bmi = $weight / (($height / 100) ** 2);
        if ($bmi < 18.5 || $bmi >= 30) {
            return $this->unavailable('review', 'Your measurements fall outside this first version’s automatic planning range. An individual review is needed to choose appropriate energy and protein targets.');
        }
        if ($profile->target_weight_kg !== null) {
            $targetWeight = (float) $profile->target_weight_kg;
            $targetBmi = $targetWeight / (($height / 100) ** 2);
            if ($profile->goal === 'lose_weight' && ($targetWeight >= $weight || $targetBmi < 18.5)) {
                return $this->unavailable('review', 'Check your target weight: it must be below your current weight without entering the underweight range.');
            }
            if ($profile->goal === 'gain_weight' && $targetWeight <= $weight) {
                return $this->unavailable('review', 'For weight gain, choose a target above your current weight.');
            }
        }
        $sexConstant = $profile->calculation_sex === 'male' ? 5 : -161;
        $resting = 10 * $weight + 6.25 * $height - 5 * $age + $sexConstant;
        $factor = $activityFactors[$profile->activity_level];
        $maintenance = $resting * $factor;
        $adjusted = match ($profile->goal) {
            'lose_weight' => $maintenance * (1 - $settings['loss_percent'] / 100),
            'gain_weight' => $maintenance + $settings['gain_surplus'],
            'build_muscle' => $maintenance + $settings['muscle_surplus'],
            default => $maintenance,
        };
        $calories = (int) (round($adjusted / 10) * 10);
        if ($calories < 1500 || $calories > 4500) {
            return $this->unavailable('review', 'The estimate is outside this version’s automatic planning range of 1,500–4,500 kcal. An individual review is needed.');
        }
        $goalSplit = NutritionCalculatorSettings::goalSplit($settings, $profile->goal);
        if (! MacroSplit::valid($goalSplit)) {
            return $this->unavailable('review', 'The administrator’s macro percentages need checking. Each goal must have a valid 100% split.');
        }
        $mode = $profile->macro_mode ?? 'goal';
        if (! in_array($mode, ['goal', 'custom'], true)) {
            return $this->unavailable('review', 'Choose goal defaults or a custom macro split in your nutrition profile.');
        }
        $split = $goalSplit;
        if ($mode === 'custom') {
            if ($profile->macro_goal !== $profile->goal) {
                return $this->unavailable('review', 'Your goal changed. Review and save your custom macro split again, or reset it to the current goal defaults.');
            }
            $split = [
                'protein' => $profile->macro_protein_percent,
                'carbohydrate' => $profile->macro_carbohydrate_percent,
                'fat' => $profile->macro_fat_percent,
            ];
            if (! MacroSplit::valid($split)) {
                return $this->unavailable('review', 'Your custom macro split must use valid percentages and total 100%.');
            }
        }
        $grams = MacroSplit::grams($calories, $split);
        return [
            'status' => 'ready', 'message' => 'Starting estimates based on your saved profile.',
            'version' => self::VERSION.' / settings '.$configuration['revision'],
            'settings' => $settings, 'age' => $age, 'resting_kcal' => $resting,
            'activity_factor' => $factor, 'maintenance_kcal' => $maintenance,
            'adjustment_kcal' => $calories - $maintenance, 'calories_kcal' => $calories,
            'protein_g' => $grams['protein_g'], 'carbohydrate_g' => $grams['carbohydrate_g'], 'fat_g' => $grams['fat_g'],
            // Compatibility: now a derived figure, not the rule used to set protein.
            'protein_factor' => $grams['protein_g'] / $weight,
            'macro_source' => $mode,
            'macro_percentages' => array_map('floatval', $split),
            'goal_macro_percentages' => array_map('floatval', $goalSplit),
        ];
    }

    private function unavailable(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message, 'version' => self::VERSION];
    }
}
