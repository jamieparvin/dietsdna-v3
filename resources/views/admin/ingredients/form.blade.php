@extends('layouts.app')

@section('title', $ingredient->exists ? 'Edit ingredient' : 'Add ingredient')

@section('content')
    @php
        $editing = $ingredient->exists;

        $details = [
            'food_code' => 'Food code',
            'name' => 'Ingredient name',
            'category' => 'Category',
            'sub_category' => 'Subcategory',
            'preparation_state' => 'Preparation state',
        ];

        $nutrients = [
            'energy_kcal' => 'Energy (kcal)',
            'protein_g' => 'Protein (g)',
            'carbohydrate_g' => 'Total carbohydrate (g)',
            'sugars_g' => 'Sugars (g)',
            'fat_g' => 'Fat (g)',
            'saturates_g' => 'Saturates (g)',
            'fibre_g' => 'Fibre (g)',
            'salt_g' => 'Salt (g)',
        ];
    @endphp

    <style>
        .ingredient-form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .ingredient-form-grid label {
            margin-top: 0;
        }

        .ingredient-form-section + .ingredient-form-section {
            margin-top: 26px;
        }

        .ingredient-form-section h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .ingredient-form-actions {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 26px;
        }

        @media (max-width: 1100px) {
            .ingredient-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .ingredient-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <h1>{{ $editing ? 'Edit ingredient' : 'Add ingredient' }}</h1>

    <p>
        Enter values per 100g edible portion.
        Total carbohydrate includes fibre.
        Leave a nutrient blank only when its value is unavailable.
    </p>

    <form
        method="POST"
        action="{{ $editing
            ? route('admin.ingredients.update', $ingredient)
            : route('admin.ingredients.store') }}"
    >
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <section class="card ingredient-form-section">
            <h2>Ingredient details</h2>

            <div class="ingredient-form-grid">
                @foreach ($details as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }}</label>

                        <input
                            id="{{ $field }}"
                            name="{{ $field }}"
                            value="{{ old($field, $ingredient->{$field}) }}"
                            maxlength="{{ $field === 'name' ? 255 : 100 }}"
                            required
                        >
                    </div>
                @endforeach
            </div>

            <p>
                Use a unique food code, such as CHICKEN-BREAST-RAW-001.
                Preparation examples: raw, dry, roasted or boiled_unsalted.
            </p>
        </section>

        <section class="card ingredient-form-section">
            <h2>Nutrition per 100g</h2>

            <div class="ingredient-form-grid">
                @foreach ($nutrients as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }}</label>

                        <input
                            type="number"
                            id="{{ $field }}"
                            name="{{ $field }}"
                            value="{{ old($field, $ingredient->{$field}) }}"
                            min="0"
                            max="{{ $field === 'energy_kcal' ? 1000 : 100 }}"
                            step="0.00001"
                        >
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card ingredient-form-section">
            <h2>Availability</h2>

            <input type="hidden" name="is_active" value="0">

            <label class="remember">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((bool) old('is_active', $ingredient->is_active))
                >
                Active ingredient
            </label>

            <p>
                Uncheck this to keep the record while making it inactive.
            </p>
        </section>

        <div class="ingredient-form-actions">
            <button type="submit">
                {{ $editing ? 'Save changes' : 'Create ingredient' }}
            </button>

            <a href="{{ route('admin.ingredients.index') }}">
                Back to ingredients
            </a>
        </div>
    </form>
@endsection