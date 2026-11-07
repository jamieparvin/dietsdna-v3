<?php

namespace App\Http\Controllers;

use App\Services\Meals\MealPreparationTime;
use App\Services\Nutrition\BodyMassIndex;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\WeightInput;
use App\Services\Onboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    public function edit(
        Request $request,
        NutritionTargetCalculator $calculator
    ): View {
        $profile = $request->user()->nutritionProfile()->first();

        return view('nutrition-profile.edit', [
            'profile' => $profile,
            'bmi' => app(BodyMassIndex::class)->forProfile($profile),
            'goals' => self::GOALS,
            'activityLevels' => self::ACTIVITY_LEVELS,
            'targets' => $calculator->calculate($profile),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $wasComplete = app(Onboarding::class)->state($request->user())['complete'];
        $profile = $request->user()->nutritionProfile()->first();
        $input = app(WeightInput::class)->kilograms($request, ['weight_kg' => $profile?->weight_kg, 'target_weight_kg' => $profile?->target_weight_kg]);
        $validated = Validator::make($input, [
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

            'snacks_per_day' => [
                'sometimes',
                'required',
                'integer',
                Rule::in([0, 1, 2]),
            ],

            'cooking_minutes' => [
                'nullable',
                'integer',
                Rule::in(MealPreparationTime::allowed($profile?->cooking_minutes)),
            ],

            'activity_level' => [
                'nullable',
                Rule::in(array_keys(self::ACTIVITY_LEVELS)),
            ],

            'calculation_sex' => [
                'nullable',
                Rule::in(['male', 'female']),
            ],

            'resistance_training' => [
                'nullable',
                'boolean',
            ],
        ], [], ['cooking_minutes' => 'time available per meal'])->validate();

        $request->user()->nutritionProfile()->updateOrCreate(
            [],
            $validated
        );

        return redirect()
            ->route($wasComplete ? 'nutrition-profile.edit' : 'setup')
            ->with('status', 'Your nutrition profile has been saved.');
    }
}
