@extends('layouts.app')

@section('title', $recipe->name)

@section('content')
    @include('admin.recipes.styles')

    <style>
        .recipe-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .recipe-description {
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .recipe-method {
            white-space: pre-line;
            overflow-wrap: anywhere;
            line-height: 1.8;
            color: #344d40;
        }

        .recipe-quantity {
            white-space: nowrap;
            text-align: right;
        }
    </style>

    <div class="recipe-head">
        <div>
            <h1>{{ $recipe->name }}</h1>

            <div class="recipe-meta">
                <span class="badge">{{ ucfirst($recipe->status) }}</span>

                <span class="badge">
                    {{ $recipe->servings }}
                    {{ $recipe->servings === 1 ? 'serving' : 'servings' }}
                </span>

                <span class="badge">
                    {{ $recipe->ingredients->count() }}
                    {{ $recipe->ingredients->count() === 1 ? 'ingredient' : 'ingredients' }}
                </span>
            </div>
        </div>

        <div class="recipe-actions">
            <a href="{{ route('admin.recipes.index') }}">
                Back to recipes
            </a>

            <a
                href="{{ route('admin.recipes.edit', $recipe) }}"
                class="recipe-button"
            >
                Edit recipe
            </a>
        </div>
    </div>

    <div class="recipe-editor">
        <div class="recipe-stack">
            @if (filled($recipe->description))
                <section class="recipe-card">
                    <h2>About this recipe</h2>
                    <p class="recipe-description">{{ $recipe->description }}</p>
                </section>
            @endif

            <section class="recipe-card">
                <h2>Ingredients</h2>

                <p class="recipe-muted">
                    Quantities are for the whole recipe.
                    Weigh each ingredient in the preparation state shown.
                </p>

                <div class="recipe-table-wrap">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Preparation</th>
                                <th class="recipe-quantity">Quantity</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recipe->ingredients as $ingredient)
                                <tr>
                                    <td>
                                        <strong>{{ $ingredient->name }}</strong>
                                        <small>{{ $ingredient->food_code }}</small>

                                        @unless ($ingredient->is_active)
                                            <small>Inactive ingredient</small>
                                        @endunless
                                    </td>

                                    <td>{{ $ingredient->preparation_state }}</td>

                                    <td class="recipe-quantity">
                                        {{ number_format(
                                            (float) $ingredient->pivot->quantity_g,
                                            2
                                        ) }} g
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">No ingredients added.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="recipe-card">
                <h2>Cooking instructions</h2>
                <div class="recipe-method">{{ $recipe->instructions }}</div>
            </section>
        </div>

        <aside class="recipe-stack">
            <section class="recipe-card">
                <h2>Nutrition summary</h2>

                <p class="recipe-muted">
                    Calculated from the saved quantities and current ingredient values.
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
                                @php
                                    $total = $recipe->ingredients->isEmpty()
                                        ? null
                                        : $totals[$field];

                                    $digits = $field === 'salt_g' ? 3 : 2;

                                    $perServing = $total !== null && $recipe->servings > 0
                                        ? $total / $recipe->servings
                                        : null;
                                @endphp

                                <tr>
                                    <td>{{ $label }}</td>

                                    <td>
                                        {{ $total === null
                                            ? '—'
                                            : number_format($total, $digits) }}
                                    </td>

                                    <td>
                                        {{ $perServing === null
                                            ? '—'
                                            : number_format($perServing, $digits) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="recipe-muted">
                    Total carbohydrate includes fibre. Unknown values display
                    as —. Nutrition is estimated from ingredient data;
                    cooking losses and changes in water weight are not calculated.
                </p>
            </section>

            <section class="recipe-card">
                <h2>Recipe details</h2>

                <p class="recipe-muted">
                    Created {{ $recipe->created_at->format('d M Y') }}<br>
                    Updated {{ $recipe->updated_at->format('d M Y') }}
                </p>

                <a
                    href="{{ route('admin.recipes.edit', $recipe) }}"
                    class="recipe-button"
                >
                    Edit recipe
                </a>
            </section>
        </aside>
    </div>
@endsection