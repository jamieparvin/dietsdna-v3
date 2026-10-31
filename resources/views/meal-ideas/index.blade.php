@extends('layouts.app')

@section('title', 'Meal ideas')

@section('content')

<style>

    .mi-page { width:100%; color:#183d32; }

    .mi-page h1 { margin:0 0 8px; font-size:30px; }

    .mi-intro { color:#697c70; margin-bottom:24px; line-height:1.7; }

    .mi-panel {

        background:#fff; border:1px solid #dfe7e1;

        border-radius:16px; padding:22px; margin-bottom:22px;

    }

    .mi-notice {

        padding:16px 20px; border-radius:12px;

        background:#edf4ee; margin-bottom:20px; line-height:1.7;

    }

    .mi-error { background:#fff0ed; color:#843f32; }

    .mi-targets {

        display:grid; grid-template-columns:repeat(4,minmax(0,1fr));

        gap:14px; margin:18px 0;

    }

    .mi-target {

        padding:17px; border-radius:12px; background:#f3f6f2;

    }

    .mi-target span { display:block; color:#6c7d70; font-size:13px; }

    .mi-target strong { display:block; margin-top:7px; font-size:23px; }

    .mi-form {

        display:flex; align-items:end; gap:16px; flex-wrap:wrap;

    }

    .mi-form label { display:block; font-weight:700; margin-bottom:8px; }

    .mi-form select {

        min-width:220px; padding:12px;

        border:1px solid #ccdacf; border-radius:9px;

        background:#fff; color:#183d32; font:inherit;

    }

    .mi-button {

        border:0; border-radius:9px; padding:13px 22px;

        color:#fff; background:#1c4936; font:inherit;

        font-weight:700; cursor:pointer;

    }

    .mi-button:disabled { opacity:.6; cursor:wait; }

    .mi-grid {

        display:grid; grid-template-columns:repeat(3,minmax(0,1fr));

        gap:20px; align-items:start;

    }

    .mi-card {

        background:#fff; border:1px solid #dfe7e1;

        border-radius:16px; padding:23px; min-width:0;

    }

    .mi-card h2 { font-size:21px; margin:0 0 12px; line-height:1.4; }

    .mi-description { color:#697c70; line-height:1.6; }

    .mi-energy { font-size:29px; font-weight:800; margin:20px 0 5px; }

    .mi-difference { color:#697c70; font-size:13px; margin-bottom:18px; }

    .mi-macros {

        display:grid; grid-template-columns:repeat(3,minmax(0,1fr));

        gap:7px; margin-bottom:22px;

    }

    .mi-macros div {

        padding:11px 5px; text-align:center;

        background:#edf3ed; border-radius:8px; font-size:12px;

    }

    .mi-macros strong { display:block; font-size:16px; margin-bottom:4px; }

    .mi-ingredients { list-style:none; padding:0; margin:0; }

    .mi-ingredients li {

        display:flex; justify-content:space-between; gap:12px;

        padding:12px 0; border-bottom:1px solid #edf0ec;

        line-height:1.5;

    }

    .mi-ingredients small { display:block; color:#728076; }

    .mi-weight { white-space:nowrap; font-weight:700; }

    .mi-card details { margin-top:20px; }

    .mi-card summary { cursor:pointer; font-weight:700; }

    .mi-method { white-space:pre-line; line-height:1.8; margin-top:14px; }

    .mi-small { font-size:13px; color:#697c70; line-height:1.7; }

    @media(max-width:1200px) {

        .mi-grid { grid-template-columns:1fr; }

    }

    @media(max-width:700px) {

        .mi-targets { grid-template-columns:repeat(2,minmax(0,1fr)); }

        .mi-form > div, .mi-form select, .mi-button { width:100%; }

        .mi-form select { box-sizing:border-box; min-width:0; }

    }

</style>

<div class="mi-page">

    <h1>Meal ideas for you</h1>

    <p class="mi-intro">

        Explore meals using your food preferences and nutrition targets.

        Each suggestion is an alternative for one meal.

    </p>

    @if($errors->any())

        <div class="mi-notice mi-error" role="alert">

            @foreach($errors->all() as $error)

                <div>{{ $error }}</div>

            @endforeach

        </div>

    @endif

    @if($portionNotice)
        <div class="mi-notice" role="status">{{ $portionNotice }}</div>
    @endif

    @if($blocked)

        <div class="mi-panel">

            <h2>Before generating meal ideas</h2>

            <p>{{ $blocked }}</p>

            <a href="{{ route('nutrition-profile.edit') }}">

                View your nutrition profile

            </a>

        </div>

    @elseif($context)

        <section class="mi-panel">

            <h2>Your starting guide for one meal</h2>

            <p class="mi-small">

                Your daily target is

                {{ number_format($context['targets']['calories_kcal']) }} kcal

                across {{ $context['meal_count'] }} full meals
                @if(($context['snack_count'] ?? 0) > 0)
                    plus {{ $context['snack_count'] }} optional {{ $context['snack_count'] === 1 ? 'snack' : 'snacks' }}
                @else
                    with no optional snacks
                @endif
                ({{ $context['meal_count'] + ($context['snack_count'] ?? 0) }} eating occasions in total).

                This {{ $context['meal_type'] }} guide receives

                {{ $context['allocation'][$context['meal_type']]['percent'] }}%

                of your daily calories and macro allocation.

                @if($context['meal_type'] === 'snack')

                    Your daily pattern includes

                    {{ $context['allocation']['snack']['count'] }}

                    {{ $context['allocation']['snack']['count'] === 1 ? 'snack' : 'snacks' }}.

                    The figures shown are for one snack.

                @endif

            </p>

            <div class="mi-targets">

                <div class="mi-target">

                    <span>Calories</span>

                    <strong>

                        {{ number_format($context['meal_targets']['energy_kcal']) }}

                        kcal

                    </strong>

                </div>

                <div class="mi-target">

                    <span>Protein</span>

                    <strong>

                        {{ number_format($context['meal_targets']['protein_g'], 1) }} g

                    </strong>

                </div>

                <div class="mi-target">

                    <span>Carbohydrate allocation</span>

                    <strong>

                        {{ number_format($context['meal_targets']['carbohydrate_g'], 1) }} g

                    </strong>

                </div>

                <div class="mi-target">

                    <span>Fat</span>

                    <strong>

                        {{ number_format($context['meal_targets']['fat_g'], 1) }} g

                    </strong>

                </div>

            </div>

            <form

                id="meal-idea-form"

                method="POST"

                action="{{ route('meal-ideas.store') }}"

                class="mi-form"

            >

                @csrf

                <div>

                    <label for="meal-type">Choose a meal</label>

                    <select id="meal-type" name="meal_type">

                        @foreach($context['allocation'] as $type => $slot)

                            <option

                                value="{{ $type }}"

                                @selected($context['meal_type'] === $type)

                            >

                                {{ $slot['label'] }}

                                — {{ number_format($slot['targets']['energy_kcal']) }} kcal

                            </option>

                        @endforeach

                    </select>

                </div>

                <button class="mi-button" id="meal-generate-button">

                    Generate meal ideas

                </button>

            </form>

            <p class="mi-small">

                Foods marked Dislike are excluded across their linked preparations.

                Generation uses your configured AI account and may take up to two minutes.

                We aim to show three suitable alternatives. If some fail the portion check, one extra AI request may be made for replacements. Only checked options are shown. Refreshing this page does not generate again.

            </p>

            <p id="meal-progress" class="mi-small" role="status" hidden>

                Creating your meal ideas. Please keep this page open…

            </p>

        </section>

        @if($batch)
            @if(count($batch['ideas']) < 3 || ($batch['generation_attempts'] ?? 1) > 1)
                <div class="mi-notice" role="status">
                    Showing {{ count($batch['ideas']) }} of 3 requested ideas.
                    @if(($batch['generation_attempts'] ?? 1) > 1)
                        One extra request was made for replacement options.
                    @endif
                    @if(($batch['replacement_unavailable'] ?? false))
                        The extra request did not complete. Your checked options are kept below.
                    @elseif(count($batch['ideas']) < 3)
                        The remaining candidates did not pass the portion checks or repeated an option.
                    @endif
                    @if(count($batch['ideas']) < 3)
                        No exclusions, macro tolerances or portion limits were relaxed. You can use the checked options below or generate a fresh batch.
                    @endif
                </div>
            @endif

            <div class="mi-notice">

                Choose one of these alternatives.

                All ingredient quantities are grams for one serving.

                Follow each ingredient’s raw or cooked weighing state.
                <details style="margin-top:10px">
                    <summary>How portions were checked</summary>
                    Each idea is checked separately: calories within 5% and each macro within 10% of this meal’s guide.
                    Carbohydrate is compared using estimated available carbohydrate (total minus fibre).
                    These are planning tolerances. Saving keeps exactly the ingredient portions shown.
                </details>

            </div>

            <div class="mi-grid">

                @foreach($batch['ideas'] as $ideaIndex => $idea)

                    <article class="mi-card">

                        <h2>{{ $idea['name'] }}</h2>

                        <p class="mi-description">{{ $idea['description'] }}</p>

                        <div class="mi-energy">

                            {{ number_format($idea['totals']['energy_kcal']) }} kcal

                        </div>

                        <div class="mi-difference">

                            {{ number_format(abs($idea['calorie_difference'])) }}

                            kcal

                            {{ $idea['calorie_difference'] >= 0 ? 'above' : 'below' }}

                            your starting meal guide

                        </div>

                        <div class="mi-macros">

                            <div>

                                <strong>

                                    {{ number_format($idea['totals']['protein_g'], 1) }} g

                                </strong>

                                Protein

                            </div>

                            <div>

                                <strong>

                                    {{ number_format($idea['totals']['carbohydrate_g'], 1) }} g

                                </strong>

                                Total carbs

                            </div>

                            <div>

                                <strong>

                                    {{ number_format($idea['totals']['fat_g'], 1) }} g

                                </strong>

                                Fat

                            </div>

                        </div>

                        <p class="mi-small">
                            Estimated available carbs: {{ number_format($idea['totals']['estimated_available_carbohydrate_g'], 1) }} g.
                            Total carbohydrate above includes fibre.
                        </p>

                        <h3>Ingredients</h3>

                        <ul class="mi-ingredients">

                            @foreach($idea['ingredients'] as $ingredient)

                                <li>

                                    <div>

                                        {{ $ingredient['name'] }}

                                        <small>

                                            {{ $ingredient['preparation_state'] }}

                                        </small>

                                    </div>

                                    <span class="mi-weight">

                                        {{ rtrim(rtrim(number_format($ingredient['quantity_g'], 2, '.', ''), '0'), '.') }} g

                                    </span>

                                </li>

                            @endforeach

                        </ul>

                        <details>

                            <summary>How to prepare</summary>

                            <div class="mi-method">{{ $idea['instructions'] }}</div>

                        </details>

                        <form

                            method="POST"

                            action="{{ route('saved-meals.store') }}"

                            style="margin-top:20px"

                        >

                            @csrf

                            <input

                                type="hidden"

                                name="idea_index"

                                value="{{ $ideaIndex }}"

                            >

                            <input

                                type="hidden"

                                name="batch_token"

                                value="{{ hash('sha256', json_encode($batch, JSON_THROW_ON_ERROR)) }}"

                            >

                            <button

                                class="mi-button"

                                type="submit"

                                @disabled(in_array($ideaIndex, $savedIdeaIndexes, true))

                            >

                                {{ in_array($ideaIndex, $savedIdeaIndexes, true) ? 'Saved ✓' : 'Save meal' }}

                            </button>

                        </form>

                    </article>

                @endforeach

            </div>

            <p class="mi-small">

                Nutrition comes from your ingredient library.

                The target carbohydrate allocation uses the calculator’s

                4/4/9 method; ingredient total carbohydrate includes fibre.

                These figures are not directly equivalent.

                Unsaved ideas remain here for 30 minutes.

                Save your favourites to keep them and add them to your meal plan.

            </p>

        @endif

    @endif

</div>

<script>

document.getElementById('meal-type')?.addEventListener('change', event => {

    const url = new URL(window.location.href);

    url.searchParams.set('meal_type', event.target.value);

    window.location.assign(url.toString());

});

document.getElementById('meal-idea-form')?.addEventListener('submit', () => {

    const button = document.getElementById('meal-generate-button');

    button.disabled = true;

    button.textContent = 'Generating…';

    document.getElementById('meal-progress').hidden = false;

});

</script>

@endsection
