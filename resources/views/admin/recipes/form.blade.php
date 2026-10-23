@extends('layouts.app')

@section('title', $recipe->exists ? 'Edit recipe' : 'Add recipe')

@section('content')
    @include('admin.recipes.styles')

    @php
        $savedRows = $recipe->ingredients->map(fn ($ingredient) => [
            'ingredient_id' => $ingredient->id,
            'quantity_g' => $ingredient->pivot->quantity_g,
        ])->values()->all();

        $rows = old('ingredients', $savedRows ?: [
            ['ingredient_id' => '', 'quantity_g' => '100'],
        ]);

        $catalog = $ingredients->mapWithKeys(fn ($ingredient) => [
            $ingredient->id => $ingredient->only(
                array_keys(\App\Models\Recipe::NUTRIENTS)
            ),
        ]);
    @endphp

    <div class="recipe-head">
        <div>
            <h1>{{ $recipe->exists ? 'Edit recipe' : 'Create a recipe' }}</h1>
            <p>Choose your ingredients, set the quantities and build your method.</p>
        </div>

        <a href="{{ route('admin.recipes.index') }}">Back to recipes</a>
    </div>

    <form
        id="recipe-form"
        method="POST"
        action="{{ $recipe->exists
            ? route('admin.recipes.update', $recipe)
            : route('admin.recipes.store') }}"
    >
        @csrf

        @if ($recipe->exists)
            @method('PUT')
        @endif

        <div class="recipe-editor">
            <div class="recipe-stack">
                <section class="recipe-card">
                    <h2>Recipe details</h2>

                    <label for="name">Recipe name</label>
                    <input
                        id="name"
                        name="name"
                        value="{{ old('name', $recipe->name) }}"
                        required
                        maxlength="255"
                        placeholder="For example, chicken and rice bowl"
                    >

                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        maxlength="5000"
                        placeholder="A short introduction to this recipe"
                    >{{ old('description', $recipe->description) }}</textarea>

                    <div class="recipe-fields">
                        <div>
                            <label for="servings">Number of servings</label>
                            <input
                                id="servings"
                                name="servings"
                                type="number"
                                min="1"
                                max="100"
                                step="1"
                                required
                                value="{{ old('servings', $recipe->servings) }}"
                            >
                        </div>

                        <div>
                            <label for="status">Recipe status</label>
                            <select id="status" name="status">
                                <option
                                    value="draft"
                                    @selected(old('status', $recipe->status) === 'draft')
                                >Draft</option>

                                <option
                                    value="published"
                                    @selected(old('status', $recipe->status) === 'published')
                                >Published</option>
                            </select>
                        </div>
                    </div>

                    <p class="recipe-muted">
                        Published marks a recipe ready for use. Customer recipe
                        browsing will be added separately.
                    </p>
                </section>

                <section class="recipe-card">
                    <h2>Ingredients</h2>

                    <p class="recipe-muted">
                        Enter grams in the preparation state shown. For example,
                        use cooked rice values when weighing cooked rice.
                        Include oils and other ingredients used in the recipe.
                    </p>

                    <div id="ingredient-rows">
                        @foreach ($rows as $index => $row)
                            <div class="recipe-row">
                                <div>
                                    <label>
                                        Ingredient
                                        <select
                                            data-field="ingredient_id"
                                            name="ingredients[{{ $index }}][ingredient_id]"
                                            required
                                        >
                                            <option value="">Choose an ingredient</option>

                                            @foreach ($ingredients as $ingredient)
                                                <option
                                                    value="{{ $ingredient->id }}"
                                                    @selected(
                                                        (string) ($row['ingredient_id'] ?? '')
                                                        === (string) $ingredient->id
                                                    )
                                                >
                                                    {{ $ingredient->name }}
                                                    · {{ $ingredient->preparation_state }}
                                                    · {{ $ingredient->food_code }}
                                                    {{ $ingredient->is_active ? '' : ' · Inactive' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>

                                <div>
                                    <label>
                                        Grams
                                        <input
                                            data-field="quantity_g"
                                            name="ingredients[{{ $index }}][quantity_g]"
                                            type="number"
                                            min="0.01"
                                            max="100000"
                                            step="0.01"
                                            required
                                            value="{{ $row['quantity_g'] ?? '' }}"
                                        >
                                    </label>
                                </div>

                                <button
                                    type="button"
                                    class="recipe-remove"
                                    aria-label="Remove ingredient row"
                                >Remove</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" id="add-ingredient">
                        + Add ingredient
                    </button>
                </section>

                <section class="recipe-card">
                    <h2>Cooking instructions</h2>

                    <label for="instructions">Method</label>
                    <textarea
                        id="instructions"
                        name="instructions"
                        rows="9"
                        maxlength="20000"
                        required
                        placeholder="1. Prepare the ingredients.&#10;2. Cook and assemble.&#10;3. Divide into servings."
                    >{{ old('instructions', $recipe->instructions) }}</textarea>
                </section>
            </div>

            <aside class="recipe-stack">
                <section class="recipe-card">
                    <h2>Nutrition summary</h2>

                    <p class="recipe-muted">
                        Updates as you change ingredients, grams or servings.
                        Based on the current ingredient library.
                    </p>

                    <div class="recipe-table-wrap">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>Nutrient</th>
                                    <th>Whole recipe</th>
                                    <th>Per serving</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach (\App\Models\Recipe::NUTRIENTS as $field => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td data-total="{{ $field }}">—</td>
                                        <td data-serving="{{ $field }}">—</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="recipe-muted" id="nutrition-message" aria-live="polite"></p>

                    <p class="recipe-muted">
                        Total carbohydrate includes fibre. Unknown values display
                        as —. Totals are estimates from ingredient data; cooking
                        losses and changes in water weight are not calculated.
                    </p>
                </section>

                <section class="recipe-card">
                    <h2>Save your recipe</h2>

                    <p class="recipe-muted">
                        Save as draft while working. Publishing requires active
                        ingredients with complete nutrition data.
                    </p>

                    <button type="submit" class="submit">
                        {{ $recipe->exists ? 'Save changes' : 'Create recipe' }}
                    </button>
                </section>
            </aside>
        </div>
    </form>

    <template id="ingredient-template">
        <div class="recipe-row">
            <div>
                <label>
                    Ingredient
                    <select data-field="ingredient_id" required>
                        <option value="">Choose an ingredient</option>

                        @foreach ($ingredients->where('is_active', true) as $ingredient)
                            <option value="{{ $ingredient->id }}">
                                {{ $ingredient->name }}
                                · {{ $ingredient->preparation_state }}
                                · {{ $ingredient->food_code }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div>
                <label>
                    Grams
                    <input
                        data-field="quantity_g"
                        type="number"
                        min="0.01"
                        max="100000"
                        step="0.01"
                        value="100"
                        required
                    >
                </label>
            </div>

            <button
                type="button"
                class="recipe-remove"
                aria-label="Remove ingredient row"
            >Remove</button>
        </div>
    </template>

    <script>
        (() => {
            const catalog = {{ Illuminate\Support\Js::from($catalog) }};
            const fields = {{ Illuminate\Support\Js::from(
                array_keys(\App\Models\Recipe::NUTRIENTS)
            ) }};
            const rows = document.getElementById('ingredient-rows');
            const template = document.getElementById('ingredient-template');
            const servingsInput = document.getElementById('servings');
            const message = document.getElementById('nutrition-message');
            const addButton = document.getElementById('add-ingredient');

            const renumber = () => {
                rows.querySelectorAll('.recipe-row').forEach((row, index) => {
                    row.querySelectorAll('[data-field]').forEach(input => {
                        input.name = `ingredients[${index}][${input.dataset.field}]`;
                    });
                });

                addButton.disabled = rows.children.length >= 100;
            };

            const calculate = () => {
                const totals = Object.fromEntries(fields.map(field => [field, 0]));
                let completeRows = 0;
                let incomplete = false;

                rows.querySelectorAll('.recipe-row').forEach(row => {
                    const id = row.querySelector('[data-field="ingredient_id"]').value;
                    const grams = Number(
                        row.querySelector('[data-field="quantity_g"]').value
                    );
                    const ingredient = catalog[id];

                    if (!ingredient || !Number.isFinite(grams) || grams <= 0) {
                        incomplete = true;
                        return;
                    }

                    completeRows++;

                    fields.forEach(field => {
                        const value = ingredient[field];

                        if (value === null || totals[field] === null) {
                            totals[field] = null;
                        } else {
                            totals[field] += Number(value) * grams / 100;
                        }
                    });
                });

                const servings = Number(servingsInput.value);
                const validServings = Number.isInteger(servings)
                    && servings >= 1 && servings <= 100;

                fields.forEach(field => {
                    const value = totals[field];
                    const known = completeRows > 0 && !incomplete && value !== null;
                    const digits = field === 'salt_g' ? 3 : 2;

                    document.querySelector(`[data-total="${field}"]`).textContent =
                        known ? value.toFixed(digits) : '—';

                    document.querySelector(`[data-serving="${field}"]`).textContent =
                        known && validServings
                            ? (value / servings).toFixed(digits)
                            : '—';
                });

                message.textContent = incomplete || completeRows === 0
                    ? 'Choose an ingredient and enter grams for every row.'
                    : !validServings
                        ? 'Enter a whole number of servings between 1 and 100.'
                        : `Calculated for ${servings} serving${servings === 1 ? '' : 's'}.`;
            };

            addButton.addEventListener('click', () => {
                if (rows.children.length >= 100) return;

                rows.append(template.content.cloneNode(true));
                renumber();
                calculate();
                rows.lastElementChild.querySelector('select').focus();
            });

            rows.addEventListener('click', event => {
                const button = event.target.closest('.recipe-remove');
                if (!button) return;

                if (rows.children.length === 1) {
                    const row = button.closest('.recipe-row');
                    row.querySelector('select').value = '';
                    row.querySelector('input').value = '100';
                } else {
                    button.closest('.recipe-row').remove();
                }

                renumber();
                calculate();
            });

            rows.addEventListener('input', calculate);
            rows.addEventListener('change', calculate);
            servingsInput.addEventListener('input', calculate);

            renumber();
            calculate();
        })();
    </script>
@endsection