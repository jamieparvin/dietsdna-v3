@extends('layouts.app')
@section('title', 'Swap planned meal')
@section('content')
@include('meals.styles')
@include('weekly-meals.styles')
<div class="wm-page">
    <h1>Swap a meal</h1>
    <p class="wm-muted">{{ $entry->plan_date->format('l, j F Y') }} · {{ ucfirst(str_replace('_', ' ', $entry->slot)) }}</p>
    <div class="wm-toolbar"><a href="{{ route('meal-plan.index', ['date' => $entry->plan_date->format('Y-m-d')]) }}">Cancel and return to meal plan</a></div>
    @if($errors->any())
        <div class="wm-notice wm-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    <section class="wm-panel" aria-labelledby="current-meal-heading">
        <h2 id="current-meal-heading">Currently planned</h2>
        @include('meals.details', ['meal' => $entry->snapshot])
    </section>
    @if($blocked)
        <div class="wm-notice wm-error" role="status">{{ $blocked }}</div>
    @else
        <section class="wm-panel" aria-labelledby="generate-swap-heading">
            <h2 id="generate-swap-heading">Find a different {{ $mealType }}</h2>
            <p>Generate fresh suggestions for this day and meal slot. You can use an idea directly without saving it first. Your current meal stays in place until you confirm a replacement.</p>
            <form method="POST" action="{{ route('meal-plan.swap.generate', $entry->id) }}">
                @csrf
                <input type="hidden" name="entry_token" value="{{ $entryToken }}">
                <button type="submit" class="wm-button">Generate {{ $mealType }} ideas</button>
            </form>
            <p class="wm-muted">Suggestions use your current food preferences and meal targets. If no suitable alternative is found, you can retry here; nothing in your plan is changed by generation.</p>
        </section>
        @if($batchNotice)
            <p class="wm-notice" role="status">{{ $batchNotice }}</p>
        @endif
        @if($batch)
            <section aria-labelledby="generated-swap-heading">
                <h2 id="generated-swap-heading">Review {{ $mealType }} suggestions</h2>
                <div class="wm-grid">
                    @foreach($batch['ideas'] as $index => $idea)
                        <article class="wm-panel wm-choice">
                            @include('meals.details', ['meal' => $idea])
                            <form method="POST" action="{{ route('meal-plan.swap.update', $entry->id) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="entry_token" value="{{ $entryToken }}">
                                <input type="hidden" name="batch_token" value="{{ $batch['batch_token'] }}">
                                <input type="hidden" name="idea_index" value="{{ $index }}">
                                <button type="submit" class="wm-button">Use this meal instead</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
        <section aria-labelledby="saved-swap-heading">
            <h2 id="saved-swap-heading">Or choose a saved meal</h2>
            <form method="GET" action="{{ route('meal-plan.swap', $entry->id) }}" class="wm-toolbar">
                <div><label for="swap-type">Show saved options</label><select name="type" id="swap-type">@foreach(['all' => 'All saved meals', 'breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snacks'] as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select></div>
                <button type="submit" class="wm-button wm-secondary">Show meals</button>
            </form>
            <p class="wm-muted">Only eligible saved alternatives are shown. The current recipe, including copies with different portions, is excluded. Saved quantities stay unchanged when you use a saved meal.</p>
            <div class="wm-grid">
                @forelse($meals as $savedMeal)
                    <article class="wm-panel wm-choice">
                        @include('meals.details', ['meal' => $savedMeal->snapshot])
                        <form method="POST" action="{{ route('meal-plan.swap.update', $entry->id) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="saved_meal_id" value="{{ $savedMeal->id }}">
                            <input type="hidden" name="entry_token" value="{{ $entryToken }}">
                            <button type="submit" class="wm-button">Use this meal instead</button>
                        </form>
                    </article>
                @empty
                    <section class="wm-panel">
                        <h3>No other eligible saved meals</h3>
                        <p>There are no other saved options in this section. Generate {{ $mealType }} ideas above to find an alternative. You do not need any saved recipes to get started.</p>
                    </section>
                @endforelse
            </div>
            <div class="wm-toolbar">@if($meals->previousPageUrl())<a href="{{ $meals->previousPageUrl() }}">Previous</a>@endif @if($meals->nextPageUrl())<a href="{{ $meals->nextPageUrl() }}">Next</a>@endif</div>
        </section>
    @endif
</div>
@endsection
