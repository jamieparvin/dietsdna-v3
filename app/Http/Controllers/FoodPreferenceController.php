<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Services\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FoodPreferenceController extends Controller
{
    public function edit(Request $request)
    {
        $saved = DB::table('user_food_preferences')
            ->where('user_id', $request->user()->id)
            ->pluck('preference', 'food_key')
            ->all();

        $ingredients = $this->foods(array_keys($saved));

        return view('food-preferences.edit', [
            'ingredients' => $ingredients,
            'saved' => $saved,
            'categories' => $ingredients
                ->pluck('category')
                ->unique()
                ->values(),
        ]);
    }

    public function update(Request $request)
    {
        $wasComplete = app(Onboarding::class)->state($request->user())['complete'];
        $validated = $request->validate([
            'preferences' => ['sometimes', 'array', 'max:1000'],
            'preferences.*' => [
                'required',
                'string',
                Rule::in(['neutral', 'preferred', 'excluded']),
            ],
        ]);

        $preferences = $validated['preferences'] ?? [];

        foreach (array_keys($preferences) as $key) {
            if (! preg_match('/^[a-f0-9]{64}$/', (string) $key)) {
                throw ValidationException::withMessages([
                    'preferences' => 'An invalid food was submitted.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $preferences) {
            $userId = $request->user()->id;

            DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            $existingKeys = DB::table('user_food_preferences')
                ->where('user_id', $userId)
                ->pluck('food_key')
                ->all();

            $allowedKeys = $this->foods($existingKeys, true)
                ->pluck('id')
                ->all();

            if (array_diff(array_keys($preferences), $allowedKeys)) {
                throw ValidationException::withMessages([
                    'preferences' => 'The food library changed. Reload the page '
                        .'and check your choices.',
                ]);
            }

            $timestamp = now();
            DB::table('users')->where('id', $userId)->update(['food_preferences_reviewed_at' => $timestamp]);

            foreach ($preferences as $key => $preference) {
                if ($preference === 'neutral') {
                    DB::table('user_food_preferences')
                        ->where('user_id', $userId)
                        ->where('food_key', $key)
                        ->delete();

                    continue;
                }

                DB::table('user_food_preferences')->upsert([
                    [
                        'user_id' => $userId,
                        'food_key' => $key,
                        'preference' => $preference,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                ], ['user_id', 'food_key'], [
                    'preference',
                    'updated_at',
                ]);
            }
        });

        return redirect()
            ->route($wasComplete ? 'food-preferences.edit' : 'setup')
            ->with('status', 'Your food preferences have been saved.');
    }

    private function foods(array $savedKeys, bool $lock = false)
    {
        return Ingredient::query()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderBy('category')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($ingredient) => $ingredient->preferenceFoodKey())
            ->map(function ($variants, $key) {
                $first = $variants->first();

                $displayCategory = $first->category;

                if (mb_strtolower(trim($first->category)) === 'protein & dairy') {
                    $displayCategory = match (
                        mb_strtolower(trim((string) $first->sub_category))
                    ) {
                        'dairy', 'eggs' => 'Dairy',
                        'plant proteins' => 'Plant proteins',
                        default => $first->category,
                    };
                }

                return (object) [
                    'id' => $key,
                    'name' => $this->foodName($first),
                    'category' => $displayCategory,
                    'is_active' => $variants->contains(
                        fn ($ingredient) => (bool) $ingredient->is_active
                    ),
                ];
            })
            ->filter(fn ($food) => $food->is_active || in_array($food->id, $savedKeys, true)
            )
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function foodName(Ingredient $ingredient): string
    {
        return trim((string) $ingredient->preference_food)
            ?: trim($ingredient->name);
    }
}
