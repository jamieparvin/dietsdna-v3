<?php

namespace App\Services\Nutrition;

use Illuminate\Support\Facades\DB;

class NutritionCalculatorSettings
{
    public static function macroGoals(): array
    {
        return [
            'eat_better' => 'Eat better',
            'lose_weight' => 'Lose weight',
            'maintain_weight' => 'Maintain weight',
            'gain_weight' => 'Gain weight',
            'build_muscle' => 'Build muscle',
        ];
    }

    public static function fields(): array
    {
        $groups = [
            'Calorie adjustments' => [
                'loss_percent' => ['Weight-loss deficit (%)', 15, 0, 25, 0.5],
                'gain_surplus' => ['Weight-gain surplus (kcal)', 300, 0, 500, 10],
                'muscle_surplus' => ['Muscle-building surplus (kcal)', 200, 0, 500, 10],
            ],
            'Activity multipliers' => [
                'activity_sedentary' => ['Mostly seated', 1.20, 1, 2.5, 0.001],
                'activity_light' => ['Light activity', 1.375, 1, 2.5, 0.001],
                'activity_moderate' => ['Moderately active', 1.55, 1, 2.5, 0.001],
                'activity_high' => ['Very active', 1.725, 1, 2.5, 0.001],
            ],
        ];
        // Planning presets from the supplied reference, not measured requirements.
        // Weight gain is separately editable and starts at the general split.
        $presets = [
            'eat_better' => [20, 50, 30],
            'lose_weight' => [35, 35, 30],
            'maintain_weight' => [30, 40, 30],
            'gain_weight' => [20, 50, 30],
            'build_muscle' => [30, 45, 25],
        ];
        foreach (self::macroGoals() as $goal => $label) {
            foreach (array_keys(MacroSplit::LIMITS) as $index => $macro) {
                [$min, $max] = MacroSplit::LIMITS[$macro];
                $groups['Macro split — '.$label]['macro_'.$goal.'_'.$macro.'_percent'] = [
                    ucfirst($macro).' (% of calories)', $presets[$goal][$index], $min, $max, 0.01,
                ];
            }
        }
        return $groups;
    }

    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::fields() as $fields) {
            foreach ($fields as $key => $definition) {
                $defaults[$key] = $definition[1];
            }
        }
        return $defaults;
    }

    public static function goalSplit(array $settings, string $goal): array
    {
        $split = [];
        foreach (array_keys(MacroSplit::LIMITS) as $macro) {
            $split[$macro] = $settings['macro_'.$goal.'_'.$macro.'_percent'] ?? null;
        }
        return $split;
    }

    public function current(): array
    {
        $row = DB::table('nutrition_calculator_settings')->where('id', 1)->first();
        if (! $row) {
            throw new \RuntimeException('Calculator settings are missing. Run php artisan migrate.');
        }
        $stored = json_decode($row->parameters, true, 512, JSON_THROW_ON_ERROR);
        $defaults = self::defaults();
        return [
            'revision' => (int) $row->revision,
            'values' => array_replace($defaults, array_intersect_key($stored, $defaults)),
        ];
    }
}
