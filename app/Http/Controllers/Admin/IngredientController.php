<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $query = Ingredient::query();

        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('food_code', 'like', "%{$search}%");
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('category', $filters['category']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where(
                'is_active',
                $filters['status'] === 'active'
            );
        }

        $ingredients = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $categories = Ingredient::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('admin.ingredients.index', compact(
            'ingredients',
            'categories',
            'filters'
        ));
    }

    public function create()
    {
        return view('admin.ingredients.form', [
            'ingredient' => new Ingredient(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $ingredient = Ingredient::create(
            $this->validatedData($request)
        );

        return redirect()
            ->route('admin.ingredients.edit', $ingredient)
            ->with('status', 'Ingredient created successfully.');
    }

    public function edit(Ingredient $ingredient)
    {
        return view('admin.ingredients.form', compact('ingredient'));
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $ingredient->update(
            $this->validatedData($request, $ingredient)
        );

        return redirect()
            ->route('admin.ingredients.edit', $ingredient)
            ->with('status', 'Ingredient updated successfully.');
    }

    private function validatedData(
        Request $request,
        ?Ingredient $ingredient = null
    ): array {
        foreach ([
            'food_code',
            'name',
            'category',
            'sub_category',
            'preparation_state',
        ] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([
                    $field => trim($request->input($field)),
                ]);
            }
        }

        if (is_string($request->input('food_code'))) {
            $request->merge([
                'food_code' => strtoupper($request->input('food_code')),
            ]);
        }

        $rules = [
            'food_code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('ingredients', 'food_code')
                    ->ignore($ingredient?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'sub_category' => ['required', 'string', 'max:100'],
            'preparation_state' => ['required', 'string', 'max:100'],
            'energy_kcal' => [
                'nullable',
                'numeric',
                'between:0,1000',
                'decimal:0,5',
            ],
            'is_active' => ['required', 'boolean'],
        ];

        foreach ([
            'protein_g',
            'carbohydrate_g',
            'sugars_g',
            'fat_g',
            'saturates_g',
            'fibre_g',
            'salt_g',
        ] as $field) {
            $rules[$field] = [
                'nullable',
                'numeric',
                'between:0,100',
                'decimal:0,5',
            ];
        }

        $validator = validator($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            foreach ([
                'sugars_g' => 'carbohydrate_g',
                'saturates_g' => 'fat_g',
            ] as $part => $total) {
                if (
                    ! $validator->errors()->has($part) &&
                    ! $validator->errors()->has($total) &&
                    is_numeric($request->input($part)) &&
                    is_numeric($request->input($total)) &&
                    (float) $request->input($part) >
                        (float) $request->input($total)
                ) {
                    $validator->errors()->add(
                        $part,
                        str_replace('_', ' ', $part).
                        ' cannot exceed '.
                        str_replace('_', ' ', $total).'.'
                    );
                }
            }
        });

        return $validator->validate();
    }

    public function destroy(Ingredient $ingredient)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($ingredient) {
            $lockedIngredient = Ingredient::query()
                ->lockForUpdate()
                ->findOrFail($ingredient->id);

            $isUsed = \Illuminate\Support\Facades\DB::table('ingredient_recipe')
                ->where('ingredient_id', $lockedIngredient->id)
                ->exists();

            if ($isUsed) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ingredient' =>
                        'This ingredient is used in a recipe and cannot be deleted. '
                        .'Remove it from the recipe first, or mark it inactive.',
                ]);
            }

            $lockedIngredient->delete();
        });

        return redirect()
            ->route('admin.ingredients.index')
            ->with('status', 'Ingredient deleted successfully.');
    }
}