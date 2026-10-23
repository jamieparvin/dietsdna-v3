<?php

namespace App\Services\Nutrition;

use Illuminate\Support\Facades\DB;

class NutritionCalculatorSettings
{
    public static function fields(): array
    {
        // Each entry: label, default, minimum, maximum, input step.
        return [
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

            'Protein allocation' => [
                'protein_general' => ['General / no resistance training (g/kg)', 1.2, 0.8, 2.5, 0.1],
                'protein_training' => ['Resistance training or muscle building (g/kg)', 1.6, 0.8, 2.5, 0.1],
                'protein_loss' => ['Weight loss without resistance training (g/kg)', 1.6, 0.8, 2.5, 0.1],
                'protein_loss_training' => ['Weight loss with resistance training (g/kg)', 2.0, 0.8, 2.5, 0.1],
            ],

            'Fat allocation' => [
                'fat_percent' => ['Calories allocated to fat (%)', 30, 20, 35, 0.5],
            ],
        ];
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

    public function current(): array
    {
        $row = DB::table('nutrition_calculator_settings')
            ->where('id', 1)
            ->first();

        if (! $row) {
            throw new \RuntimeException(
                'Calculator settings are missing. Run php artisan migrate.'
            );
        }

        $stored = json_decode(
            $row->parameters,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $defaults = self::defaults();

        return [
            'revision' => (int) $row->revision,
            'values' => array_replace(
                $defaults,
                array_intersect_key($stored, $defaults)
            ),
        ];
    }
}