<?php

namespace App\Http\Controllers;

use App\Services\Meals\MealPreparationTime;
use App\Services\Nutrition\BodyMassIndex;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\PlanningSuitability;
use App\Services\Nutrition\WeightInput;
use App\Services\Onboarding;
use Closure;
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

            'planning_context' => [
                'nullable', 'string',
                Rule::in([...array_keys(PlanningSuitability::ANSWERS), 'needs_review']),
            ],

            'planning_circumstances_present' => ['sometimes', 'required', Rule::in([1])],
            'planning_circumstances' => [
                'sometimes', 'array', 'list', 'max:5',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && in_array('none', $value, true) && count($value) > 1) {
                        $fail('Select None apply on its own, or select the circumstances that apply to you.');
                    }
                },
            ],
            'planning_circumstances.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(PlanningSuitability::CIRCUMSTANCES))],

            'resistance_training' => [
                'nullable',
                'boolean',
            ],
        ], [
            'planning_circumstances.array' => 'Select the circumstances that apply to you.',
            'planning_circumstances.*.in' => 'Choose only the listed circumstances.',
            'planning_circumstances.*.distinct' => 'Select each circumstance only once.',
        ], ['cooking_minutes' => 'time available per meal', 'planning_context' => 'specialist nutrition support answer', 'planning_circumstances' => 'planning suitability selections'])->after(function ($validator) use ($input): void {
            if ((array_key_exists('planning_circumstances', $input) || array_key_exists('planning_circumstances_present', $input)) && array_key_exists('planning_context', $input)) {
                $validator->errors()->add('planning_circumstances', 'Submit one suitability answer format at a time.');
            }
        })->validate();

        if (array_key_exists('planning_circumstances', $validated) || array_key_exists('planning_circumstances_present', $validated)) {
            $selections = $validated['planning_circumstances'] ?? [];
            if ($selections === [] && PlanningSuitability::needsClarification($profile)) {
                unset($validated['planning_circumstances']);
            } else {
                $validated['planning_circumstances'] = $selections;
                $validated['planning_context'] = PlanningSuitability::context($selections);
            }
        } elseif (array_key_exists('planning_context', $validated)) {
            $savedAnswer = PlanningSuitability::answer($profile?->planning_context, $profile?->planning_circumstances);
            if ($validated['planning_context'] === null && in_array($savedAnswer, ['specialist_support', 'unsure'], true)) {
                unset($validated['planning_context']);
            } elseif (PlanningSuitability::answer($validated['planning_context']) !== $savedAnswer) {
                $validated['planning_circumstances'] = null;
            }
        }
        unset($validated['planning_circumstances_present']);

        $request->user()->nutritionProfile()->updateOrCreate(
            [],
            $validated
        );

        return redirect()
            ->route($wasComplete ? 'nutrition-profile.edit' : 'setup')
            ->with('status', 'Your nutrition profile has been saved.');
    }
}
