@extends('layouts.app')

@section('title', 'Ingredients')

@section('content')
    <style>
        .ingredient-heading,
        .ingredient-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .ingredient-button {
            display: inline-block;
            padding: 13px 20px;
            background: #167451;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .ingredient-filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 14px;
            align-items: end;
            margin: 26px 0;
        }

        .ingredient-filters label {
            margin: 0 0 8px;
        }

        .ingredient-filters select {
            width: 100%;
            padding: 13px;
            border: 1px solid #b9cbc0;
            border-radius: 8px;
            background: white;
            font: inherit;
        }

        .ingredient-table-wrap {
            overflow-x: auto;
        }

        .ingredient-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .ingredient-table th,
        .ingredient-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #e5ece7;
            vertical-align: top;
        }

        .ingredient-table th {
            color: #61756a;
            font-size: 12px;
            white-space: nowrap;
        }

        .ingredient-table small {
            display: block;
            margin-top: 6px;
            color: #61756a;
            overflow-wrap: anywhere;
        }

        .ingredient-table .numeric {
            text-align: right;
            white-space: nowrap;
        }

        .ingredient-pagination {
            margin-top: 22px;
        }

        @media (max-width: 1100px) {
            .ingredient-filters {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .ingredient-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ingredient-heading">
        <div>
            <h1>Ingredients</h1>
            <p>Manage your food library. Nutritional values are per 100g.</p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a
                href="{{ route('admin.ingredients.import.create') }}"
                class="ingredient-button"
                style="background: #153f32;"
            >
                Import Excel
            </a>

            <a
                href="{{ route('admin.ingredients.create') }}"
                class="ingredient-button"
            >
                Add ingredient
            </a>
        </div>
    </div>

    <form
        method="GET"
        action="{{ route('admin.ingredients.index') }}"
        class="ingredient-filters"
    >
        <div>
            <label for="search">Search ingredients</label>
            <input
                id="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Name or food code"
                maxlength="100"
            >
        </div>

        <div>
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>

                @foreach ($categories as $category)
                    <option
                        value="{{ $category }}"
                        @selected(($filters['category'] ?? '') === $category)
                    >
                        {{ $category }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option
                    value="active"
                    @selected(($filters['status'] ?? '') === 'active')
                >
                    Active
                </option>
                <option
                    value="inactive"
                    @selected(($filters['status'] ?? '') === 'inactive')
                >
                    Inactive
                </option>
            </select>
        </div>

        <button type="submit">Filter</button>

        <a href="{{ route('admin.ingredients.index') }}">Reset</a>
    </form>

    <div class="card">
        <div class="ingredient-table-wrap">
            <table class="ingredient-table">
                <thead>
                    <tr>
                        <th scope="col">Ingredient</th>
                        <th scope="col">Category</th>
                        <th scope="col">Preparation</th>
                        <th scope="col" class="numeric">kcal</th>
                        <th scope="col" class="numeric">Protein (g)</th>
                        <th scope="col" class="numeric">Carbs (g)</th>
                        <th scope="col" class="numeric">Fat (g)</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <small>{{ $ingredient->food_code }}</small>
                            </td>

                            <td>
                                {{ $ingredient->category }}
                                <small>{{ $ingredient->sub_category }}</small>
                            </td>

                            <td>
                                {{ ucfirst(str_replace('_', ' ', $ingredient->preparation_state)) }}
                            </td>

                            @foreach (['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g'] as $field)
                                <td class="numeric">
                                    {{ $ingredient->{$field} === null
                                        ? '—'
                                        : number_format((float) $ingredient->{$field}, 2) }}
                                </td>
                            @endforeach

                            <td>
                                <span class="badge">
                                    {{ $ingredient->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <td>
                                <a
                                    href="{{ route('admin.ingredients.edit', $ingredient) }}"
                                    aria-label="Edit {{ $ingredient->name }}"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                No ingredients found. Add an ingredient to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ingredient-pagination">
            <span>
                {{ number_format($ingredients->total()) }} ingredients
                · Page {{ $ingredients->currentPage() }}
                of {{ $ingredients->lastPage() }}
            </span>

            <div>
                @if ($ingredients->previousPageUrl())
                    <a href="{{ $ingredients->previousPageUrl() }}">
                        Previous
                    </a>
                @endif

                @if ($ingredients->hasMorePages())
                    <a
                        href="{{ $ingredients->nextPageUrl() }}"
                        style="margin-left: 18px;"
                    >
                        Next
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection