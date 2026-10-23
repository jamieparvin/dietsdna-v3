<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);

        $recipes = Recipe::query()
            ->withCount('ingredients')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.recipes.index', compact('recipes'));
    }

    public function create()
    {
        $recipe = new Recipe([
            'servings' => 1,
            'status' => 'draft',
        ]);

        $recipe->setRelation('ingredients', collect());

        return $this->form($recipe);
    }

    public function store(Request $request)
    {
        $recipe = $this->saveRecipe($request, new Recipe());

        return redirect()
            ->route('admin.recipes.edit', $recipe)
            ->with('status', 'Recipe created successfully.');
    }

    public function show(Recipe $recipe)
    {
        $recipe->load('ingredients');

        $totals = $recipe->nutritionTotals();

        return view('admin.recipes.show', compact('recipe', 'totals'));
    }

    public function edit(Recipe $recipe)
    {
        $recipe->load('ingredients');

        return $this->form($recipe);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $this->saveRecipe($request, $recipe);

        return redirect()
            ->route('admin.recipes.edit', $recipe)
            ->with('status', 'Recipe updated successfully.');
    }

    public function destroy(Recipe $recipe)
    {
        DB::transaction(function () use ($recipe) {
            $lockedRecipe = Recipe::query()
                ->lockForUpdate()
                ->findOrFail($recipe->id);

            $lockedRecipe->delete();
        });

        return redirect()
            ->route('admin.recipes.index')
            ->with('status', 'Recipe deleted successfully.');
    }

    private function form(Recipe $recipe)
    {
        $existingIds = $recipe->ingredients->pluck('id')->all();

        $ingredients = Ingredient::query()
            ->where(function ($query) use ($existingIds) {
                $query->where('is_active', true)
                    ->orWhereIn('id', $existingIds);
            })
            ->orderBy('name')
            ->orderBy('preparation_state')
            ->get();

        return view('admin.recipes.form', compact(
            'recipe',
            'ingredients',
        ));
    }

    private function saveRecipe(Request $request, Recipe $recipe): Recipe
    {
        $existingIds = $recipe->exists
            ? $recipe->ingredients()->pluck('ingredients.id')->all()
            : [];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['required', 'string', 'max:20000'],
            'servings' => ['required', 'integer', 'between:1,100'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*' => ['required', 'array:ingredient_id,quantity_g'],
            'ingredients.*.ingredient_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('ingredients', 'id')->where(
                    fn ($query) => $query->where(
                        fn ($query) => $query
                            ->where('is_active', true)
                            ->orWhereIn('id', $existingIds)
                    )
                ),
            ],
            'ingredients.*.quantity_g' => [
                'required',
                'numeric',
                'between:0.01,100000',
                'decimal:0,2',
            ],
        ], [
            'ingredients.*.ingredient_id.distinct' =>
                'Use each ingredient once. Combine its quantities into one row.',
            'ingredients.*.ingredient_id.exists' =>
                'Choose an available ingredient from the list.',
        ]);

        return DB::transaction(function () use ($data, $recipe) {
            if ($recipe->exists) {
                $recipe = Recipe::query()
                    ->lockForUpdate()
                    ->findOrFail($recipe->id);
            }

            $rows = array_values($data['ingredients']);

            $selected = Ingredient::query()
                ->whereIn('id', array_column($rows, 'ingredient_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($rows as $position => $row) {
                $ingredient = $selected->get($row['ingredient_id']);

                if (! $ingredient) {
                    throw ValidationException::withMessages([
                        'ingredients' => 'An ingredient is no longer available.',
                    ]);
                }

                if ($data['status'] === 'published') {
                    if (! $ingredient->is_active) {
                        throw ValidationException::withMessages([
                            'ingredients' =>
                                'Replace inactive ingredients before publishing.',
                        ]);
                    }

                    foreach (array_keys(Recipe::NUTRIENTS) as $field) {
                        if ($ingredient->{$field} === null) {
                            throw ValidationException::withMessages([
                                'ingredients' =>
                                    "{$ingredient->name} has missing nutrition data. "
                                    .'Complete it before publishing.',
                            ]);
                        }
                    }
                }
            }

            unset($data['ingredients']);

            $recipe->fill($data);
            $recipe->save();

            $pivot = [];

            foreach ($rows as $position => $row) {
                $pivot[$row['ingredient_id']] = [
                    'quantity_g' => $row['quantity_g'],
                    'position' => $position,
                ];
            }

            $recipe->ingredients()->sync($pivot);

            return $recipe;
        });
    }
}