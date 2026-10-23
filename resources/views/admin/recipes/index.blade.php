@extends('layouts.app')

@section('title', 'Recipes')

@section('content')
    @include('admin.recipes.styles')

    <div class="recipe-head">
        <div>
            <h1>Recipes</h1>
            <p>Build your recipe library using your ingredients.</p>
        </div>

        <div class="recipe-actions">
            <a
                href="{{ route('admin.recipes.generate.create') }}"
                class="recipe-button"
            >
                Generate recipes
            </a>

            <a
                href="{{ route('admin.recipes.create') }}"
                class="recipe-button"
                style="background: #167451;"
            >
                Add recipe
            </a>
        </div>
    </div>

    <form method="GET" class="recipe-filter">
        <div>
            <label for="search">Search recipes</label>
            <input
                id="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Recipe name"
            >
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option value="draft" @selected(request('status') === 'draft')>
                    Draft
                </option>
                <option value="published" @selected(request('status') === 'published')>
                    Published
                </option>
            </select>
        </div>

        <button type="submit">Filter</button>
        <a href="{{ route('admin.recipes.index') }}">Reset</a>
    </form>

    <div class="recipe-card">
        <div class="recipe-table-wrap">
            <table class="recipe-table">
                <thead>
                    <tr>
                        <th>Recipe</th>
                        <th>Ingredients</th>
                        <th>Servings</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($recipes as $recipe)
                        <tr>
                            <td>
                                <strong>{{ $recipe->name }}</strong>
                                <small>
                                    Updated {{ $recipe->updated_at->format('d M Y') }}
                                </small>
                            </td>
                            <td>{{ $recipe->ingredients_count }}</td>
                            <td>{{ $recipe->servings }}</td>
                            <td>
                                <span class="badge">
                                    {{ ucfirst($recipe->status) }}
                                </span>
                            </td>
                                <td>
                                    <div class="recipe-actions" style="justify-content: flex-start;">
                                        <a href="{{ route('admin.recipes.show', $recipe) }}">
                                            View
                                        </a>

                                        <a href="{{ route('admin.recipes.edit', $recipe) }}">
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.recipes.destroy', $recipe) }}"
                                            onsubmit="return confirm('Permanently delete this recipe? This cannot be undone.');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                style="background: #fff0ee; color: #8b2920; padding: 8px 12px;"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No recipes found. Add your first recipe to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="recipe-pagination">
            <span class="recipe-muted">
                {{ $recipes->total() }} recipes
                · Page {{ $recipes->currentPage() }} of {{ $recipes->lastPage() }}
            </span>

            <div class="recipe-actions">
                @if ($recipes->previousPageUrl())
                    <a href="{{ $recipes->previousPageUrl() }}">Previous</a>
                @endif

                @if ($recipes->nextPageUrl())
                    <a href="{{ $recipes->nextPageUrl() }}">Next</a>
                @endif
            </div>
        </div>
    </div>
@endsection