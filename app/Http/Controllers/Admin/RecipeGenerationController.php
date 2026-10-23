<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\Recipes\RecipeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeGenerationController extends Controller
{
    public function create()
    {
        $ingredients = $this->eligibleIngredients()->get();

        return view('admin.recipes.generate', compact('ingredients'));
    }

    public function store(Request $request, RecipeGenerator $generator)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'between:1,5'],
            'brief' => ['required', 'string', 'max:2000'],
            'ingredient_ids' => ['nullable', 'array', 'max:200'],
            'ingredient_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $lock = Cache::lock(
            'recipe-generation:user:'.$request->user()->id,
            90
        );

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'generation' =>
                    'A generation request is already running for your account.',
            ]);
        }

        try {
            // Allow the bounded API request time to finish on local Windows PHP.
            set_time_limit(90);

            $query = $this->eligibleIngredients();

            if (! empty($data['ingredient_ids'])) {
                $query->whereIn('id', $data['ingredient_ids']);
            }

            $catalog = $query->get();

            if (
                ! empty($data['ingredient_ids'])
                && $catalog->count() !== count($data['ingredient_ids'])
            ) {
                throw ValidationException::withMessages([
                    'generation' =>
                        'A selected ingredient is inactive or has incomplete nutrition.',
                ]);
            }

            if ($catalog->isEmpty() || $catalog->count() > 200) {
                throw ValidationException::withMessages([
                    'generation' =>
                        'Choose between 1 and 200 eligible ingredients.',
                ]);
            }

            $recipes = $generator->generate(
                $catalog,
                (int) $data['count'],
                $data['brief']
            );

            $snapshots = $catalog->keyBy('id');

            DB::transaction(function () use ($recipes, $snapshots) {
                $usedIds = collect($recipes)
                    ->flatMap(fn ($recipe) =>
                        array_column($recipe['ingredients'], 'ingredient_id')
                    )
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $current = Ingredient::query()
                    ->whereIn('id', $usedIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($usedIds as $id) {
                    $ingredient = $current->get($id);
                    $snapshot = $snapshots->get($id);

                    if (
                        ! $ingredient
                        || ! $snapshot
                        || $this->signature($ingredient)
                            !== $this->signature($snapshot)
                    ) {
                        throw ValidationException::withMessages([
                            'generation' =>
                                'An ingredient changed during generation. '
                                .'Nothing was saved. Review the library and try again.',
                        ]);
                    }
                }

                foreach ($recipes as $draft) {
                    $recipe = Recipe::create([
                        'name' => trim($draft['name']),
                        'description' => $draft['description'],
                        'instructions' => $draft['instructions'],
                        'servings' => $draft['servings'],
                        'status' => 'draft',
                    ]);

                    $pivot = [];

                    foreach ($draft['ingredients'] as $position => $row) {
                        $pivot[$row['ingredient_id']] = [
                            'quantity_g' => $row['quantity_g'],
                            'position' => $position,
                        ];
                    }

                    $recipe->ingredients()->sync($pivot);
                }
            });

            return redirect()
                ->route('admin.recipes.index', ['status' => 'draft'])
                ->with(
                    'status',
                    count($recipes).' generated recipes saved as drafts. '
                    .'Review ingredients, quantities and instructions before publishing.'
                );
        } finally {
            $lock->release();
        }
    }

    private function eligibleIngredients()
    {
        $query = Ingredient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id');

        foreach (array_keys(Recipe::NUTRIENTS) as $field) {
            $query->whereNotNull($field);
        }

        return $query;
    }

    private function signature(Ingredient $ingredient): string
    {
        return hash('sha256', json_encode(
            $ingredient->only([
                'name',
                'food_code',
                'preparation_state',
                'is_active',
                ...array_keys(Recipe::NUTRIENTS),
            ]),
            JSON_THROW_ON_ERROR
        ));
    }
}