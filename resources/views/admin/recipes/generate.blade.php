@extends('layouts.app')

@section('title', 'Generate recipes')

@section('content')
    @include('admin.recipes.styles')

    <div class="recipe-head">
        <div>
            <h1>Generate recipes</h1>
            <p>Create recipe drafts using your ingredient library.</p>
        </div>

        <a href="{{ route('admin.recipes.index') }}">Back to recipes</a>
    </div>

    <form
        id="recipe-generation-form"
        method="POST"
        action="{{ route('admin.recipes.generate.store') }}"
    >
        @csrf

        <div class="recipe-editor">
            <div class="recipe-stack">
                <section class="recipe-card">
                    <h2>What would you like to create?</h2>

                    <label for="brief">Recipe brief</label>

                    <textarea
                        id="brief"
                        name="brief"
                        rows="6"
                        maxlength="2000"
                        required
                        placeholder="For example: simple chicken and rice lunches, with clear batch-cooking instructions."
                    >{{ old('brief') }}</textarea>

                    <p class="recipe-muted">
                        Describe the meals you want. This first version does not
                        enforce calorie ranges or certify dietary or allergy suitability.
                        Do not include customer personal or medical information.
                    </p>

                    <label for="count">Number of recipes</label>

                    <select id="count" name="count" required>
                        @foreach (range(1, 5) as $count)
                            <option
                                value="{{ $count }}"
                                @selected((int) old('count', 1) === $count)
                            >
                                {{ $count }} {{ $count === 1 ? 'recipe' : 'recipes' }}
                            </option>
                        @endforeach
                    </select>
                </section>

                <section class="recipe-card">
                    <h2>Available ingredients</h2>

                    <p class="recipe-muted">
                        {{ $ingredients->count() }} active ingredients have complete
                        nutrition data. Leave the list unselected to allow all of them.
                        To limit the generator, hold Ctrl and select ingredients.
                    </p>

                    <label for="ingredient_ids">Limit to these ingredients</label>

                    <select
                        id="ingredient_ids"
                        name="ingredient_ids[]"
                        multiple
                        size="12"
                    >
                        @foreach ($ingredients as $ingredient)
                            <option
                                value="{{ $ingredient->id }}"
                                @selected(in_array(
                                    (string) $ingredient->id,
                                    array_map('strval', old('ingredient_ids', [])),
                                    true
                                ))
                            >
                                {{ $ingredient->name }}
                                · {{ $ingredient->preparation_state }}
                                · {{ $ingredient->food_code }}
                            </option>
                        @endforeach
                    </select>
                </section>
            </div>

            <aside class="recipe-stack">
                <section class="recipe-card">
                    <h2>Generate draft recipes</h2>

                    <p>
                        AI proposes ingredients, quantities and instructions.
                        DietsDNA calculates nutrition from your stored ingredient values.
                    </p>

                    <p class="recipe-muted">
                        Every generated recipe is saved as a draft.
                        Review preparation states, portion sizes and cooking
                        instructions before publishing.
                    </p>

                    <p class="recipe-muted">
                        This sends the brief and eligible ingredient catalog to
                        OpenAI and uses your API account. Each submission is a
                        generation request. Requests are not automatically retried.
                    </p>

                    <button
                        type="submit"
                        id="generate-button"
                        class="submit"
                        @disabled($ingredients->isEmpty())
                    >
                        Generate and save drafts
                    </button>

                    <p
                        id="generation-status"
                        class="recipe-muted"
                        role="status"
                    ></p>
                </section>
            </aside>
        </div>
    </form>

    <script>
        document.getElementById('recipe-generation-form')
            .addEventListener('submit', function () {
                const button = document.getElementById('generate-button');

                button.disabled = true;
                button.textContent = 'Generating recipes…';

                document.getElementById('generation-status').textContent =
                    'Please keep this page open while the request completes.';
            });
    </script>
@endsection