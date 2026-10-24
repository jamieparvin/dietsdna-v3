@extends('layouts.app')
@section('title', 'Generate my week')
@section('content')
@include('weekly-meals.styles')
<div class="wm-page">
    <h1>Generate my week</h1>
    <p class="wm-muted">Meal ideas for seven days, with your food choices and saved portions in mind.</p>
    <div class="wm-toolbar"><a href="{{ route('meal-plan.index', ['date' => $start->toDateString()]) }}">Back to meal plan</a></div>
    @if($errors->any())
        <div class="wm-notice wm-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    @if($blocked)
        <div class="wm-panel"><p>{{ $blocked }}</p><a href="{{ route('nutrition-profile.edit') }}">View nutrition profile</a></div>
    @else
        <section class="wm-panel">
            <h2>Build a weekly preview</h2>
            <p class="wm-muted">Your current profile has {{ $source['context']['meal_count'] }} eating occasions per day: {{ 7 * $source['context']['meal_count'] }} slots across the week. Existing planned meals will be kept.</p>
            <form method="POST" action="{{ route('weekly-meals.store') }}" id="weekly-start-form">
                @csrf
                <label for="weekly-start">Choose a date in the week</label>
                <div class="wm-toolbar">
                    <input id="weekly-start" type="date" name="start_date" value="{{ old('start_date', $start->toDateString()) }}" required>
                    <button class="wm-button" id="weekly-start-button">Generate weekly preview</button>
                </div>
            </form>
            <p class="wm-muted">
                The preview runs Monday to Sunday. Meal ideas repeat across the week.
                Ingredient quantities are adjusted to bring new daily plans close to
                your calorie and macro guides. Actual totals are shown before you confirm.
            </p>
            <p class="wm-muted">Generation uses your configured AI account and can take several minutes. It runs in the background. Nothing is added to your plan until you confirm the preview.</p>
        </section>
    @endif
    @if($recent->isNotEmpty())
        <section class="wm-panel"><h2>Recent weekly previews</h2>
            @foreach($recent as $item)
                <p><a href="{{ route('weekly-meals.show', $item->id) }}">Week of {{ $item->start_date->format('j M Y') }}</a> <span class="wm-muted">· {{ ucfirst($item->status) }}</span></p>
            @endforeach
        </section>
    @endif
</div>
<script>document.getElementById('weekly-start-form')?.addEventListener('submit',()=>{const b=document.getElementById('weekly-start-button');b.disabled=true;b.textContent='Starting…';});</script>
@endsection
