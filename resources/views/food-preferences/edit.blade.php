@extends('layouts.app')

@section('title', 'Food preferences')

@section('content')
@php
    $choices = old('preferences', $saved);

    /*
     * Match existing ingredient categories to the display headings.
     * Unmatched categories remain visible after the main categories.
     */
    $categoryAliases = [
        'meat' => 'Meat & Poultry',
        'meats' => 'Meat & Poultry',
        'poultry' => 'Meat & Poultry',
        'meat and poultry' => 'Meat & Poultry',
        'meat & poultry' => 'Meat & Poultry',
        'fish' => 'Fish & Seafood',
        'seafood' => 'Fish & Seafood',
        'fish and seafood' => 'Fish & Seafood',
        'fish & seafood' => 'Fish & Seafood',
        'dairy' => 'Dairy',
        'dairy and eggs' => 'Dairy',
        'dairy & eggs' => 'Dairy',
        'carbohydrate' => 'Carbohydrate',
        'carbohydrates' => 'Carbohydrate',
        'carbs' => 'Carbohydrate',
        'fat' => 'Fats',
        'fats' => 'Fats',
        'fats and oils' => 'Fats',
        'fats & oils' => 'Fats',
        'vegetable' => 'Veg',
        'vegetables' => 'Veg',
        'veg' => 'Veg',
        'fruit' => 'Fruit',
        'fruits' => 'Fruit',
    ];

    $categoryOrder = [
        'Meat & Poultry',
        'Fish & Seafood',
        'Dairy',
        'Plant proteins',
        'Carbohydrate',
        'Fats',
        'Veg',
        'Fruit',
    ];

    $groups = $ingredients->groupBy(function ($ingredient) use ($categoryAliases) {
        $category = trim((string) $ingredient->category);
        $key = mb_strtolower($category);

        return $categoryAliases[$key] ?? ($category ?: 'Other foods');
    })->sortBy(function ($items, $category) use ($categoryOrder) {
        $position = array_search($category, $categoryOrder, true);

        return $position === false ? 999 : $position;
    });

    $options = [
        'preferred' => 'Like',
        'excluded' => 'Dislike',
        'neutral' => 'Unspecified',
    ];
@endphp

<style>
    .fp-page {
        width: 100%;
        color: #183d32;
    }

    .fp-heading {
        margin-bottom: 24px;
    }

    .fp-heading h1 {
        margin: 0 0 8px;
        font-size: 30px;
        letter-spacing: -.7px;
    }

    .fp-heading p {
        margin: 0;
        color: #6b7c74;
        line-height: 1.6;
    }

    .fp-notice {
        padding: 15px 18px;
        margin-bottom: 20px;
        border: 1px solid #dce6df;
        border-radius: 12px;
        background: #edf4ee;
        line-height: 1.7;
        font-size: 14px;
    }

    .fp-status {
        margin-bottom: 18px;
        padding: 14px 18px;
        border-radius: 12px;
        background: #e4f2e8;
        color: #245b38;
    }

    .fp-errors {
        margin-bottom: 18px;
        padding: 14px 18px;
        border: 1px solid #ebc9c4;
        border-radius: 12px;
        background: #fff3f1;
        color: #873f35;
    }

    .fp-errors ul {
        margin: 0;
        padding-left: 20px;
    }

    .fp-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 250px;
        gap: 16px;
        padding: 20px;
        margin-bottom: 28px;
        border: 1px solid #e1e8e3;
        border-radius: 16px;
        background: #fff;
    }

    .fp-field label {
        display: block;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 700;
    }

    .fp-field input,
    .fp-field select {
        box-sizing: border-box;
        width: 100%;
        min-height: 46px;
        padding: 10px 13px;
        border: 1px solid #d3dfd7;
        border-radius: 9px;
        background: #fff;
        color: #183d32;
        font: inherit;
    }

    .fp-field input:focus,
    .fp-field select:focus {
        outline: 2px solid #9ebdaa;
        outline-offset: 2px;
    }

    .fp-category {
        margin-bottom: 32px;
    }

    .fp-category-heading {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }

    .fp-category-heading h2 {
        margin: 0;
        font-size: 21px;
        letter-spacing: -.3px;
    }

    .fp-category-count {
        padding: 5px 10px;
        border-radius: 20px;
        background: #e8eee9;
        color: #617368;
        font-size: 12px;
        white-space: nowrap;
    }

    .fp-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .fp-card {
        min-width: 0;
        padding: 20px;
        border: 1px solid #dfe7e1;
        border-radius: 15px;
        background: #fff;
        transition: border-color .15s, background .15s;
    }

    .fp-card[data-choice="preferred"] {
        border-color: #8bb59a;
        background: #f5faf6;
    }

    .fp-card[data-choice="excluded"] {
        border-color: #dbb5ac;
        background: #fff9f7;
    }

    .fp-card h3 {
        margin: 0 0 7px;
        font-size: 16px;
        line-height: 1.4;
    }

    .fp-preparation {
        margin: 0 0 18px;
        color: #718076;
        font-size: 13px;
        line-height: 1.5;
    }

    .fp-inactive {
        display: inline-block;
        margin-bottom: 10px;
        padding: 4px 8px;
        border-radius: 6px;
        background: #f2e9d8;
        color: #785e2a;
        font-size: 12px;
    }

    .fp-choice-group {
        display: grid;
        grid-template-columns: 1fr 1fr 1.35fr;
        gap: 6px;
        margin: 0;
        padding: 0;
        border: 0;
        min-width: 0;
    }

    .fp-choice {
        position: relative;
        min-width: 0;
        cursor: pointer;
    }

    .fp-choice input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
    }

    .fp-choice span {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        box-sizing: border-box;
        padding: 8px 5px;
        border: 1px solid #dce4de;
        border-radius: 8px;
        background: #fff;
        color: #69776e;
        font-size: 12px;
        font-weight: 600;
        transition: background .15s, border-color .15s;
    }

    .fp-choice:hover span {
        border-color: #8fa997;
    }

    .fp-choice input:focus-visible + span {
        outline: 3px solid #9ebdaa;
        outline-offset: 2px;
    }

    .fp-choice input[value="preferred"]:checked + span {
        border-color: #245840;
        background: #245840;
        color: #fff;
    }

    .fp-choice input[value="excluded"]:checked + span {
        border-color: #934f42;
        background: #934f42;
        color: #fff;
    }

    .fp-choice input[value="neutral"]:checked + span {
        border-color: #d8d9cc;
        background: #efefe5;
        color: #4f5b4d;
    }

    .fp-footer {
        position: sticky;
        bottom: 16px;
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 22px;
        margin-top: 24px;
        border: 1px solid #d8e2da;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 30px rgba(25, 54, 39, .08);
    }

    .fp-summary {
        color: #65776a;
        font-size: 14px;
        line-height: 1.6;
    }

    .fp-summary strong {
        color: #214b36;
    }

    .fp-save {
        padding: 13px 22px;
        border: 0;
        border-radius: 9px;
        background: #1c4936;
        color: #fff;
        font: inherit;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;
    }

    .fp-save:hover {
        background: #123b29;
    }

    .fp-empty {
        padding: 30px;
        border: 1px dashed #cbd8ce;
        border-radius: 14px;
        text-align: center;
        color: #6b7c70;
        background: #fff;
    }

    .fp-page [hidden] {
        display: none !important;
    }

    .fp-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    @media (max-width: 1200px) {
        .fp-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .fp-grid,
        .fp-toolbar {
            grid-template-columns: 1fr;
        }

        .fp-heading h1 {
            font-size: 25px;
        }

        .fp-footer {
            align-items: stretch;
            flex-direction: column;
            gap: 12px;
            bottom: 8px;
        }

        .fp-save {
            width: 100%;
        }
    }
</style>

<div class="fp-page">
    <header class="fp-heading">
        <h1>Your food preferences</h1>
        <p>Choose the foods you enjoy and those you would like left out.</p>
    </header>

    @if(session('status'))
        <div class="fp-status" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="fp-errors" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="fp-notice">
        <strong>Like</strong> favours a food.
        <strong>Dislike</strong> marks it to leave out.
        <strong>Unspecified</strong> keeps it available.
        Your choice applies to all linked raw and cooked versions of the food.
        Food preferences are for ingredient choices and do not provide allergy protection.
    </div>

    <div class="fp-toolbar">
        <div class="fp-field">
            <label for="food-search">Find a food</label>
            <input
                id="food-search"
                type="search"
                placeholder="Search chicken, rice, almonds..."
                autocomplete="off"
            >
        </div>

        <div class="fp-field">
            <label for="food-category">Category</label>
            <select id="food-category">
                <option value="">All categories</option>

                @foreach($groups as $category => $items)
                    <option value="{{ $category }}">
                        {{ $category }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <form method="POST" action="{{ route('food-preferences.update') }}">
        @csrf
        @method('PUT')

        @foreach($groups as $category => $items)
            <section class="fp-category" data-category="{{ $category }}">
                <div class="fp-category-heading">
                    <h2>{{ $category }}</h2>
                    <span class="fp-category-count">
                        {{ $items->count() }}
                        {{ $items->count() === 1 ? 'food' : 'foods' }}
                    </span>
                </div>

                <div class="fp-grid">
                    @foreach($items as $ingredient)
                        @php
                            $choice = $choices[$ingredient->id] ?? 'neutral';

                            if (! array_key_exists($choice, $options)) {
                                $choice = 'neutral';
                            }
                        @endphp

                <article
                    class="fp-card"
                    data-choice="{{ $choice }}"
                    data-search="{{ mb_strtolower($ingredient->name) }}"
                >

                            @if(! $ingredient->is_active)
                                <span class="fp-inactive">Currently unavailable</span>
                            @endif

                            <h3>{{ $ingredient->name }}</h3>

                            <fieldset class="fp-choice-group">
                                <legend class="fp-sr-only">
                                    Your preference for {{ $ingredient->name }}
                                </legend>

                                @foreach($options as $value => $label)
                                    <label class="fp-choice">
                                        <input
                                            type="radio"
                                            name="preferences[{{ $ingredient->id }}]"
                                            value="{{ $value }}"
                                            @checked($choice === $value)
                                        >
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div id="food-empty" class="fp-empty" hidden>
            No foods match your search.
        </div>

        @if($ingredients->isNotEmpty())
            <div class="fp-footer">
                <div class="fp-summary" aria-live="polite">
                    <strong id="food-liked-count">0</strong> liked
                    &nbsp;·&nbsp;
                    <strong id="food-disliked-count">0</strong> disliked
                    <span id="food-unsaved" hidden>
                        &nbsp;·&nbsp; Unsaved changes
                    </span>
                </div>

                <button type="submit" class="fp-save">
                    Save preferences
                </button>
            </div>
        @else
            <div class="fp-empty">
                No foods are available yet.
            </div>
        @endif
    </form>
</div>

<script>
(() => {
    const page = document.querySelector('.fp-page');

    if (!page) return;

    const search = page.querySelector('#food-search');
    const category = page.querySelector('#food-category');
    const sections = [...page.querySelectorAll('.fp-category')];
    const cards = [...page.querySelectorAll('.fp-card')];
    const empty = page.querySelector('#food-empty');
    const likedCount = page.querySelector('#food-liked-count');
    const dislikedCount = page.querySelector('#food-disliked-count');
    const unsaved = page.querySelector('#food-unsaved');

    function filterFoods() {
        const term = search.value.trim().toLowerCase();
        const selectedCategory = category.value;
        let visibleTotal = 0;

        sections.forEach(section => {
            const matchesCategory = !selectedCategory
                || section.dataset.category === selectedCategory;

            let visibleInSection = 0;

            section.querySelectorAll('.fp-card').forEach(card => {
                const matchesSearch = card.dataset.search.includes(term);
                const visible = matchesCategory && matchesSearch;

                card.hidden = !visible;

                if (visible) visibleInSection++;
            });

            section.hidden = visibleInSection === 0;
            visibleTotal += visibleInSection;

            section.querySelector('.fp-category-count').textContent =
                `${visibleInSection} ${visibleInSection === 1 ? 'food' : 'foods'}`;
        });

        empty.hidden = visibleTotal > 0 || cards.length === 0;
    }

    function updateCounts() {
        let liked = 0;
        let disliked = 0;

        cards.forEach(card => {
            const selected = card.querySelector('input:checked');
            const value = selected ? selected.value : 'neutral';

            card.dataset.choice = value;

            if (value === 'preferred') liked++;
            if (value === 'excluded') disliked++;
        });

        if (likedCount) likedCount.textContent = liked;
        if (dislikedCount) dislikedCount.textContent = disliked;
    }

    page.querySelectorAll('.fp-choice input').forEach(input => {
        input.addEventListener('change', () => {
            updateCounts();

            if (unsaved) unsaved.hidden = false;
        });
    });

    search.addEventListener('input', filterFoods);
    category.addEventListener('change', filterFoods);

    updateCounts();
    filterFoods();
})();
</script>
@endsection