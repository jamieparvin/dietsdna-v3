<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NutritionProfileController extends Controller
{
    private const GOALS = [
        'eat_better' => 'Build healthier eating habits',
        'lose_weight' => 'Lose weight',
        'maintain_weight' => 'Maintain weight',
        'gain_weight' => 'Gain weight',
        'build_muscle' => 'Build muscle',
    ];

    private const ACTIVITY_LEVELS = [
        'sedentary' => 'Mostly seated, little regular exercise',
        'light' => 'Light activity',
        'moderate' => 'Moderately active',
        'high' => 'Very active',
    ];

    private const DIETARY_PREFERENCES = [
        'no_preference' => 'No particular preference',
        'vegetarian' => 'Vegetarian',
        'vegan' => 'Vegan',
        'pescatarian' => 'Pescatarian',
        'flexitarian' => 'Flexitarian',
    ];

    public function edit(Request $request): View
    {
        return view('nutrition-profile.edit', [
            'profile' => $request->user()->nutritionProfile,
            'goals' => self::GOALS,
            'activityLevels' => self::ACTIVITY_LEVELS,
            'dietaryPreferences' => self::DIETARY_PREFERENCES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'goal' => [
                'nullable',
                Rule::in(array_keys(self::GOALS)),
            ],

            'height_cm' => [
                'nullable', 'numeric', 'between:50,300',
            ],

            'weight_kg' => [
                'nullable', 'numeric', 'between:20,500',
            ],

            'date_of_birth' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:'.now()->timezone('Europe/London')->subYears(18)->toDateString(),
                'after_or_equal:'.now()->timezone('Europe/London')->subYears(120)->toDateString(),
            ],

            'target_weight_kg' => [
                'nullable',
                'numeric',
                'between:20,500',
            ],

            'meals_per_day' => [
                'nullable',
                'integer',
                Rule::in([2, 3, 4, 5, 6]),
            ],

            'cooking_minutes' => [
                'nullable',
                'integer',
                Rule::in([15, 30, 45, 60]),
            ],

            'activity_level' => [
                'nullable',
                Rule::in(array_keys(self::ACTIVITY_LEVELS)),
            ],

            'dietary_preference' => [
                'nullable',
                Rule::in(array_keys(self::DIETARY_PREFERENCES)),
            ],
            
            'allergies' => ['nullable', 'string', 'max:2000'],
            'foods_to_avoid' => ['nullable', 'string', 'max:2000'],
        ]);

        $request->user()->nutritionProfile()->updateOrCreate(
            [],
            $validated
        );

        return redirect()
            ->route('nutrition-profile.edit')
            ->with('status', 'Your nutrition profile has been saved.');
    }
}