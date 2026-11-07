<?php

namespace App\Services;

use App\Models\User;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPlanSlots;
use App\Services\Nutrition\NutritionTargetCalculator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Onboarding
{
    public function __construct(private readonly NutritionTargetCalculator $calculator) {}

    public function generationBlock(User $user): ?string
    {
        try {
            app(MealIdeaGenerator::class)->context($user, 'lunch');
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first();
        }

        return null;
    }

    /**
     * Review means details are recorded, even when automatic targets are unavailable.
     * Ingredient eligibility and preview generation never determine setup completion.
     *
     * @return array{targets: array, profileComplete: bool, preferencesComplete: bool, complete: bool, step: int, route: string}
     */
    public function state(User $user): array
    {
        $profile = $user->nutritionProfile()->first();
        $targets = $this->calculator->calculate($profile);
        try {
            $pattern = MealPlanSlots::forCount((int) $profile?->meals_per_day, (int) ($profile?->snacks_per_day ?? 0));
        } catch (InvalidArgumentException) {
            $pattern = [];
        }
        $profileComplete = $targets['status'] !== 'incomplete' && $pattern !== [];
        $preferencesComplete = $user->newQuery()->whereKey($user->id)->whereNotNull('food_preferences_reviewed_at')->exists();

        return [
            'targets' => $targets,
            'profileComplete' => $profileComplete,
            'preferencesComplete' => $preferencesComplete,
            'complete' => $profileComplete && $preferencesComplete,
            'step' => ! $profileComplete ? 1 : (! $preferencesComplete ? 2 : 3),
            'route' => ! $profileComplete ? 'nutrition-profile.edit' : (! $preferencesComplete ? 'food-preferences.edit' : 'weekly-meals.create'),
        ];
    }
}
