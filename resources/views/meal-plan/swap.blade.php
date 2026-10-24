@extends('layouts.app')
@section('title', 'Swap planned meal')
@section('content')
@include('meals.styles')
@include('weekly-meals.styles')
<div class="wm-page">
    <h1>Swap a meal</h1>
    <p class="wm-muted">{{ $entry->plan_date->format('l, j F Y') }} · {{ ucfirst(str_replace('_', ' ', $entry->slot)) }}</p>
    <div class="wm-toolbar"><a href="{{ route('meal-plan.index', ['date' => $entry->plan_date->format('Y-m-d')]) }}">Cancel and return to meal plan</a></div>
    @if($errors->any())<div class="wm-notice wm-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <section class="wm-panel"><h2>Currently planned</h2>@include('meals.details', ['meal' => $entry->snapshot])</section>
    @if($blocked)
        <div class="wm-notice wm-error">{{ $blocked }}</div>
    @else
        <form method="GET" action="{{ route('meal-plan.swap', $entry->id) }}" class="wm-toolbar"><div><label for="swap-type">Show saved options</label><select name="type" id="swap-type">@foreach(['all' => 'All saved meals', 'breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snacks'] as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select></div><button class="wm-button wm-secondary">Show meals</button></form>
        <p class="wm-muted">Only eligible meals from your own Saved meals are shown. Selecting a replacement changes this slot only; quantities stay as saved.</p>
        <div class="wm-grid">
            @forelse($meals as $savedMeal)
                <article class="wm-panel wm-choice">@include('meals.details', ['meal' => $savedMeal->snapshot])<form method="POST" action="{{ route('meal-plan.swap.update', $entry->id) }}">@csrf @method('PUT')<input type="hidden" name="saved_meal_id" value="{{ $savedMeal->id }}"><input type="hidden" name="entry_token" value="{{ $entryToken }}"><button class="wm-button">Use this meal instead</button></form></article>
            @empty
                <section class="wm-panel"><p>No eligible saved alternatives in this section yet.</p><a href="{{ route('meal-ideas.index', ['meal_type' => str_starts_with($entry->slot, 'snack_') ? 'snack' : $entry->slot]) }}">Generate and save another meal idea</a></section>
            @endforelse
        </div>
        <div class="wm-toolbar">@if($meals->previousPageUrl())<a href="{{ $meals->previousPageUrl() }}">Previous</a>@endif @if($meals->nextPageUrl())<a href="{{ $meals->nextPageUrl() }}">Next</a>@endif</div>
    @endif
</div>
@endsection
