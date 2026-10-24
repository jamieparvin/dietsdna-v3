<?php

namespace App\Http\Controllers;

use App\Models\SavedMeal;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPlanSlots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavedMealController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $validated['date']
            ?? now('Europe/London')->toDateString();

        $profile = $request->user()->nutritionProfile()->first();

        $slots = MealPlanSlots::forCount(
            (int) $profile?->meals_per_day
        );

        $sections = [
            'breakfast' => 'Breakfast options',
            'lunch' => 'Lunch options',
            'dinner' => 'Dinner options',
            'snack' => 'Snack options',
        ];

        $mealGroups = [];

        foreach ($sections as $type => $label) {
            $meals = SavedMeal::query()
                ->where('user_id', $request->user()->id)
                ->where('meal_type', $type)
                ->latest('id')
                ->paginate(6, ['*'], $type.'_page')
                ->withQueryString()
                ->fragment($type);

            $mealGroups[$type] = [
                'label' => $label,
                'meals' => $meals,
            ];
        }

        return view('saved-meals.index', compact(
            'mealGroups',
            'slots',
            'date'
        ));
    }

    public function store(
        Request $request,
        MealIdeaGenerator $generator
    ) {
        $data = $request->validate([
            'idea_index' => ['required', 'integer', 'between:0,2'],
            'batch_token' => ['required', 'string', 'size:64'],
        ]);

        DB::transaction(function () use ($request, $generator, $data) {
            $user = $request->user();

            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            $batch = $request->session()->get(
                'meal_ideas.user_'.$user->id
            );

            if (
                ! is_array($batch)
                || ($batch['created_at'] ?? 0)
                    < now()->subMinutes(30)->timestamp
            ) {
                $this->fail(
                    'These temporary ideas have expired. Generate a new batch.'
                );
            }

            $token = hash(
                'sha256',
                json_encode($batch, JSON_THROW_ON_ERROR)
            );

            if (! hash_equals($token, $data['batch_token'])) {
                $this->fail(
                    'A different batch is now open. Reload Meal ideas '
                    .'and select the meal again.'
                );
            }

            $context = $generator->context(
                $user,
                $batch['meal_type']
            );

            if ($context['signature'] !== $batch['signature']) {
                $this->fail(
                    'Your profile, preferences or ingredients changed. '
                    .'Generate fresh ideas before saving.'
                );
            }

            $idea = $batch['ideas'][$data['idea_index']] ?? null;

            if (! is_array($idea)) {
                $this->fail('That meal idea is no longer available.');
            }

            // Attach stable ingredient IDs to the displayed snapshot.
            // This also supports ideas generated before saving was added.
            foreach ($idea['ingredients'] as &$row) {
                $matches = $context['ingredients']->filter(
                    fn ($ingredient) =>
                        $ingredient->name === $row['name']
                        && $ingredient->preparation_state
                            === $row['preparation_state']
                );

                if ($matches->count() !== 1) {
                    $this->fail(
                        'An ingredient could not be identified uniquely. '
                        .'Check the ingredient library before saving this meal.'
                    );
                }

                $ingredient = $matches->first();

                $row['ingredient_id'] = (int) $ingredient->id;
                $row['food_key'] = MealIdeaGenerator::foodKey($ingredient);
            }

            unset($row);

            // The difference belongs to the old target, not the saved food.
            unset($idea['calorie_difference']);

            $hash = hash('sha256', json_encode(
                $idea,
                JSON_THROW_ON_ERROR
            ));

            SavedMeal::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'content_hash' => $hash,
                ],
                [
                    'meal_type' => $batch['meal_type'],
                    'snapshot' => $idea,
                ]
            );
        });

        return redirect()
            ->route('meal-ideas.index', [
                'meal_type' => $request->session()->get(
                    'meal_ideas.user_'.$request->user()->id.'.meal_type',
                    'lunch'
                ),
            ])
            ->with('status', 'Meal saved. You can save another idea below.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'meal' => $message,
        ]);
    }
}