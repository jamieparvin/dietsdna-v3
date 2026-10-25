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
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:20px;
        }

        .ingredient-form-grid > div {
            min-width:0;
        }

        .ingredient-form-grid label {
            margin-top:0;
        }

        .ingredient-form-section + .ingredient-form-section {
            margin-top:26px;
        }

        .ingredient-form-section h2 {
            margin-top:0;
            font-size:20px;
        }

        .ingredient-form-select {
            width:100%;
            min-height:44px;
            box-sizing:border-box;
            padding:12px;
            border:1px solid #b9cbc0;
            border-radius:8px;
            background:#fff;
            color:#17332c;
            font:inherit;
        }

        .ingredient-form-select:focus-visible {
            outline:3px solid #9fbea9;
            outline-offset:3px;
        }

        .ingredient-form-actions {
            display:flex;
            flex-wrap:wrap;
            gap:20px;
            align-items:center;
            margin-top:26px;
        }

        .ingredient-form-error {
            color:#984b3e;
            font-size:13px;
        }

        .ingredient-form-errors {
            padding:16px;
            margin-bottom:20px;
            border:1px solid #eaccc3;
            border-radius:10px;
            background:#fff5f2;
            color:#874b3c;
        }

        .ingredient-form-errors ul {
            margin:0;
            padding-left:20px;
        }

        @media(max-width:1100px) {
            .ingredient-form-grid {
                grid-template-columns:repeat(2,minmax(0,1fr));
            }
        }

        @media(max-width:600px) {
            .ingredient-form-grid {
                grid-template-columns:1fr;
            }
        }
    </style>

    <h1>{{ $editing ? 'Edit ingredient' : 'Add ingredient' }}</h1>

    <p>
        Enter values per 100g edible portion.
        Total carbohydrate includes fibre.
        Leave a nutrient blank only when its value is unavailable.
    </p>

    @if($errors->any())
        <div class="ingredient-form-errors" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $editing
            ? route('admin.ingredients.update', $ingredient)
            : route('admin.ingredients.store') }}"
    >
        @csrf

        @if($editing)
            @method('PUT')
        @endif

        <section class="card ingredient-form-section">
            <h2>Ingredient details</h2>

            <div class="ingredient-form-grid">
                @foreach($details as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }}</label>

                        <input
                            type="text"
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
            <h2>Linked food preferences</h2>

            @if($linkedPreparations->isNotEmpty())
                <p>
                    Currently linked:
                    {{ $linkedPreparations->pluck('name')->join(', ') }}.
                </p>
            @endif

            <label for="link_ingredient_id">
                Link to an existing food group
            </label>

            <select
                class="ingredient-form-select"
                id="link_ingredient_id"
                name="link_ingredient_id"
                aria-describedby="link-preference-help"
            >
                <option value="">
                    {{ $editing
                        ? 'Keep current group'
                        : 'Separate food — no automatic linking' }}
                </option>

                @foreach($preferenceGroups as $group)
                    @if(!$editing || $group->preferenceFoodKey() !== $ingredient->preferenceFoodKey())
                        <option
                            value="{{ $group->id }}"
                            @selected((string) old('link_ingredient_id') === (string) $group->id)
                        >
                            {{ $group->preference_food ?: $group->name }}
                            — {{ $group->food_code }}
                        </option>
                    @endif
                @endforeach
            </select>

            <p id="link-preference-help">
                Choose explicitly when this is another preparation of the same
                food. Linking joins both entire groups for every user.
                Dislikes take precedence over likes.
                Names and categories never create new links automatically.
                Saved and planned meals keep their snapshots.
            </p>
        </section>

        <section class="card ingredient-form-section">
            <h2>Shopping list conversion</h2>

            <p>
                Choose the ingredient to buy and the multiplier used to convert
                this ingredient’s planned weight into its purchase weight.
                Leave both fields blank if the conversion is not configured.
            </p>

            <div class="ingredient-form-grid">
                <div>
                    <label for="shopping_ingredient_id">
                        Ingredient to buy
                    </label>

                    <select
                        class="ingredient-form-select"
                        id="shopping_ingredient_id"
                        name="shopping_ingredient_id"
                        aria-describedby="shopping-conversion-help"
                    >
                        <option value="">Not configured</option>

                        @foreach($shoppingIngredients as $food)
                            <option
                                value="{{ $food->id }}"
                                @selected(
                                    (string) old(
                                        'shopping_ingredient_id',
                                        $ingredient->shopping_ingredient_id
                                    ) === (string) $food->id
                                )
                            >
                                {{ $food->name }}
                                — {{ $food->preparation_state }}
                                — {{ $food->food_code }}
                                {{ $food->is_active ? '' : '(inactive)' }}
                            </option>
                        @endforeach
                    </select>

                    @error('shopping_ingredient_id')
                        <p class="ingredient-form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="shopping_multiplier">
                        Purchase weight multiplier
                    </label>

                    <input
                        type="number"
                        id="shopping_multiplier"
                        name="shopping_multiplier"
                        value="{{ old('shopping_multiplier', $ingredient->shopping_multiplier) }}"
                        min="0.000001"
                        max="100"
                        step="0.000001"
                        inputmode="decimal"
                        aria-describedby="shopping-conversion-help"
                    >

                    @error('shopping_multiplier')
                        <p class="ingredient-form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <p id="shopping-conversion-help">
                Planned grams × multiplier = grams to buy.
                Use a verified conversion for the specific food and preparation.
                For an existing ingredient purchased in its own listed form,
                select that same ingredient and use 1.
                This does not change meal portions or nutrition.
            </p>
        </section>

        <section class="card ingredient-form-section">
            <h2>Nutrition per 100g</h2>

            <div class="ingredient-form-grid">
                @foreach($nutrients as $field => $label)
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