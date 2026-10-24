@extends('layouts.app')

@section('title', 'Meal plan')

@section('content')
@include('meals.styles')

@php
    $hasTargets = $targets['status'] === 'ready';

    $calorieTarget = $hasTargets
        ? (float) $targets['calories_kcal']
        : null;

    $caloriePercent = $calorieTarget > 0
        ? ($totals['energy_kcal'] / $calorieTarget) * 100
        : 0;

    $calorieDifference = $hasTargets
        ? $calorieTarget - $totals['energy_kcal']
        : null;

    $filledCount = $entries->count();
    $slotCount = count($slots);
@endphp

<style>
    .pl-page {
        width:100%;
        color:#183d32;
    }

    .pl-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        flex-wrap:wrap;
        gap:20px;
        margin-bottom:26px;
    }

    .pl-eyebrow {
        margin:0 0 8px;
        color:#738575;
        font-size:11px;
        font-weight:700;
        letter-spacing:1.5px;
        text-transform:uppercase;
    }

    .pl-header h1 {
        margin:0 0 9px;
        font-size:31px;
        letter-spacing:-.7px;
    }

    .pl-muted {
        margin:0;
        color:#6b7d71;
        line-height:1.7;
    }

    .pl-actions {
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:10px;
    }

    .pl-button {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:11px 17px;
        border:1px solid #d5e0d8;
        border-radius:9px;
        background:#fff;
        color:#234d37;
        font:inherit;
        font-size:14px;
        font-weight:600;
        text-decoration:none;
        cursor:pointer;
    }

    .pl-button-primary {
        border-color:#1c4936;
        background:#1c4936;
        color:#fff;
    }

    .pl-button:hover {
        filter:brightness(.97);
    }

    .pl-button:focus-visible,
    .pl-day:focus-visible {
        outline:3px solid #9fbea9;
        outline-offset:3px;
    }

    .pl-week {
        padding:22px;
        margin-bottom:24px;
        border:1px solid #dfe7e1;
        border-radius:17px;
        background:#fff;
    }

    .pl-week-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        flex-wrap:wrap;
        gap:15px;
        margin-bottom:20px;
    }

    .pl-week-title {
        font-size:18px;
        font-weight:700;
    }

    .pl-jump {
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:8px;
    }

    .pl-jump label {
        color:#6b7d71;
        font-size:13px;
    }

    .pl-jump input {
        padding:9px 10px;
        border:1px solid #d5e0d8;
        border-radius:8px;
        background:#fff;
        color:#183d32;
        font:inherit;
        font-size:13px;
    }

    .pl-days {
        display:grid;
        grid-template-columns:repeat(7,minmax(0,1fr));
        gap:10px;
    }

    .pl-day {
        display:flex;
        flex-direction:column;
        align-items:center;
        padding:15px 8px;
        border:1px solid #e0e7e1;
        border-radius:12px;
        background:#fafbf8;
        color:#274936;
        text-decoration:none;
        text-align:center;
    }

    .pl-day-weekday {
        font-size:12px;
        font-weight:600;
    }

    .pl-day-number {
        margin:6px 0;
        font-size:25px;
        font-weight:750;
    }

    .pl-day-detail {
        margin-top:4px;
        font-size:11px;
        line-height:1.5;
    }

    .pl-day-today {
        margin-left:3px;
        font-size:10px;
    }

    .pl-day.is-selected {
        border-color:#1c4936;
        background:#1c4936;
        color:#fff;
    }

    .pl-day:not(.is-selected):hover {
        background:#edf4ee;
        border-color:#adc5b4;
    }

    .pl-summary {
        display:grid;
        grid-template-columns:1.35fr repeat(3,minmax(0,1fr));
        gap:16px;
        margin-bottom:27px;
    }

    .pl-stat {
        min-width:0;
        padding:21px;
        border:1px solid #dfe7e1;
        border-radius:14px;
        background:#fff;
    }

    .pl-stat-calories {
        background:#edf4ee;
        border-color:#d4e2d7;
    }

    .pl-stat-label {
        color:#6c7e70;
        font-size:13px;
    }

    .pl-stat-value {
        display:block;
        margin-top:9px;
        font-size:28px;
        font-weight:750;
        letter-spacing:-.5px;
    }

    .pl-stat-note {
        margin-top:8px;
        color:#6c7e70;
        font-size:12px;
        line-height:1.6;
    }

    .pl-progress {
        height:7px;
        margin-top:15px;
        overflow:hidden;
        border-radius:10px;
        background:#d7e4d9;
    }

    .pl-progress span {
        display:block;
        height:100%;
        border-radius:10px;
        background:#3b7150;
    }

    .pl-day-heading {
        display:flex;
        justify-content:space-between;
        align-items:center;
        flex-wrap:wrap;
        gap:12px;
        margin-bottom:19px;
    }

    .pl-day-heading h2 {
        margin:0;
        font-size:23px;
    }

    .pl-completion {
        padding:7px 12px;
        border-radius:20px;
        background:#e8eee8;
        color:#607564;
        font-size:12px;
    }

    .pl-grid {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        align-items:start;
        gap:20px;
    }

    .pl-slot {
        min-width:0;
        overflow:hidden;
        border:1px solid #dfe7e1;
        border-radius:15px;
        background:#fff;
    }

    .pl-slot-heading {
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        padding:16px 21px;
        border-bottom:1px solid #e8eee8;
        background:#f7f9f4;
    }

    .pl-slot-heading h3 {
        margin:0;
        font-size:16px;
    }

    .pl-slot-status {
        color:#6c7f71;
        font-size:12px;
        white-space:nowrap;
    }

    .pl-slot-body {
        padding:22px;
    }

    .pl-slot-body > h3 {
        margin:0 0 12px;
        font-size:19px;
        line-height:1.5;
    }

    .pl-slot-body .mp-nutrition {
        margin-top:16px;
        color:#245039;
        font-size:14px;
    }

    .pl-slot-body .mp-muted {
        font-size:14px;
    }

    .pl-slot-footer {
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-top:22px;
        padding-top:16px;
        border-top:1px solid #edf0ec;
    }

    .pl-remove {
        padding:0;
        border:0;
        background:none;
        color:#8d5145;
        font:inherit;
        font-size:13px;
        cursor:pointer;
    }

    .pl-empty {
        display:flex;
        flex-direction:column;
        align-items:flex-start;
        justify-content:center;
        min-height:160px;
        box-sizing:border-box;
        padding:25px;
    }

    .pl-empty-title {
        margin:0 0 8px;
        font-size:17px;
        font-weight:600;
    }

    .pl-empty .pl-button {
        margin-top:18px;
    }

    .pl-footnote {
        margin-top:24px;
        color:#758477;
        font-size:12px;
        line-height:1.8;
    }

    @media(max-width:1100px) {
        .pl-summary {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }
    }

    @media(max-width:850px) {
        .pl-grid {
            grid-template-columns:1fr;
        }

        .pl-days {
            display:flex;
            overflow-x:auto;
            padding-bottom:8px;
        }

        .pl-day {
            flex:0 0 90px;
        }
    }

    @media(max-width:550px) {
        .pl-header h1 {
            font-size:26px;
        }

        .pl-week {
            padding:15px;
        }

        .pl-stat {
            padding:16px;
        }

        .pl-stat-value {
            font-size:23px;
        }
    }
</style>

<div class="pl-page">
    <header class="pl-header">
        <div>
            <p class="pl-eyebrow">Your week, your choices</p>
            <h1>Your meal plan</h1>

            <p class="pl-muted">
                Build your day from the meals you enjoy.
            </p>
        </div>

        <div class="pl-actions">
            <a
                class="pl-button"
                href="{{ route('meal-ideas.index') }}"
            >
                Find meal ideas
            </a>

            <a
                class="pl-button pl-button-primary"
                href="{{ route('saved-meals.index', ['date' => $date]) }}"
            >
                Browse saved meals
            </a>
        </div>
    </header>

    @if($errors->any())
        <div class="mp-errors" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="pl-week" aria-label="Choose a day">
        <div class="pl-week-header">
            <div>
                <div class="pl-week-title">
                    {{ $weekStart->format('j M') }}
                    – {{ $weekEnd->format('j M Y') }}
                </div>
            </div>

            <div class="pl-actions">
                <a
                    class="pl-button"
                    href="{{ route('meal-plan.index', ['date' => $previousWeekDate]) }}"
                    aria-label="Previous week"
                >
                    ← Previous
                </a>

                <a
                    class="pl-button"
                    href="{{ route('meal-plan.index', ['date' => $today->toDateString()]) }}"
                >
                    Today
                </a>

                <a
                    class="pl-button"
                    href="{{ route('meal-plan.index', ['date' => $nextWeekDate]) }}"
                    aria-label="Next week"
                >
                    Next →
                </a>
            </div>

            <form
                method="GET"
                action="{{ route('meal-plan.index') }}"
                class="pl-jump"
            >
                <label for="plan-date">Jump to date</label>

                <input
                    id="plan-date"
                    type="date"
                    name="date"
                    value="{{ $date }}"
                    required
                >

                <button class="pl-button" type="submit">Go</button>
            </form>
        </div>

        <nav class="pl-days" aria-label="Days of the week">
            @foreach($days as $day)
                <a
                    href="{{ route('meal-plan.index', ['date' => $day['date']]) }}"
                    class="pl-day {{ $day['is_selected'] ? 'is-selected' : '' }}"
                    @if($day['is_selected']) aria-current="date" @endif
                >
                    <span class="pl-day-weekday">
                        {{ $day['weekday'] }}

                        @if($day['is_today'])
                            <span class="pl-day-today">· Today</span>
                        @endif
                    </span>

                    <span class="pl-day-number">{{ $day['number'] }}</span>

                    <span class="pl-day-detail">
                        {{ $day['meal_count'] }}
                        {{ $day['meal_count'] === 1 ? 'meal' : 'meals' }}
                    </span>

                    <span class="pl-day-detail">
                        {{ number_format($day['calories']) }} kcal
                    </span>
                </a>
            @endforeach
        </nav>
    </section>

    <div class="pl-summary">
        <section class="pl-stat pl-stat-calories">
            <span class="pl-stat-label">Planned calories</span>

            <strong class="pl-stat-value">
                {{ number_format($totals['energy_kcal']) }} kcal
            </strong>

            @if($hasTargets)
                <div class="pl-stat-note">
                    Daily guide: {{ number_format($calorieTarget) }} kcal
                </div>

                <div
                    class="pl-progress"
                    role="img"
                    aria-label="{{ number_format($caloriePercent) }} percent of daily calorie guide planned"
                >
                    <span
                        style="width:{{ min(100, max(0, $caloriePercent)) }}%"
                    ></span>
                </div>

                <div class="pl-stat-note">
                    {{ number_format(abs($calorieDifference)) }} kcal
                    {{ $calorieDifference >= 0 ? 'below' : 'above' }}
                    the daily guide
                </div>
            @else
                <div class="pl-stat-note">Total from your selected meals</div>
            @endif
        </section>

        <section class="pl-stat">
            <span class="pl-stat-label">Protein</span>

            <strong class="pl-stat-value">
                {{ number_format($totals['protein_g'], 1) }} g
            </strong>

            @if($hasTargets)
                <div class="pl-stat-note">
                    Daily guide:
                    {{ number_format($targets['protein_g'], 1) }} g
                </div>
            @endif
        </section>

        <section class="pl-stat">
            <span class="pl-stat-label">Total carbohydrate</span>

            <strong class="pl-stat-value">
                {{ number_format($totals['carbohydrate_g'], 1) }} g
            </strong>

            @if($hasTargets)
                <div class="pl-stat-note">
                    Daily carbohydrate allocation:
                    {{ number_format($targets['carbohydrate_g'], 1) }} g.
                    See calculation note below.
                </div>
            @endif
        </section>

        <section class="pl-stat">
            <span class="pl-stat-label">Fat</span>

            <strong class="pl-stat-value">
                {{ number_format($totals['fat_g'], 1) }} g
            </strong>

            @if($hasTargets)
                <div class="pl-stat-note">
                    Daily guide:
                    {{ number_format($targets['fat_g'], 1) }} g
                </div>
            @endif
        </section>
    </div>

    @if(!$hasTargets)
        <p class="pl-muted" style="margin-bottom:22px">
            {{ $targets['message'] }}
        </p>
    @endif

    <div class="pl-day-heading">
        <h2>{{ $selectedDate->format('l, j F') }}</h2>

        <span class="pl-completion">
            {{ $filledCount }} of {{ $slotCount }} slots filled
        </span>
    </div>

    <div class="pl-grid">
        @forelse($slots as $key => $label)
            @php($entry = $entries->get($key))

            <section class="pl-slot">
                <div class="pl-slot-heading">
                    <h3>{{ $label }}</h3>

                    <span class="pl-slot-status">
                        {{ $entry ? 'Planned' : 'Available' }}
                    </span>
                </div>

                @if($entry)
                    <div class="pl-slot-body">
                        @include('meals.details', [
                            'meal' => $entry->snapshot,
                        ])

                        <div class="pl-slot-footer">
                            <span class="pl-muted" style="font-size:12px">
                                One saved portion
                            </span>

                            <form
                                method="POST"
                                action="{{ route('meal-plan.destroy', $entry->id) }}"
                            >
                                @csrf
                                @method('DELETE')

                                <button class="pl-remove" type="submit">
                                    Remove from this day
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="pl-empty">
                        <p class="pl-empty-title">
                            What would you like for {{ strtolower($label) }}?
                        </p>

                        <p class="pl-muted">
                            Choose one of your saved meal options.
                        </p>

                        <a
                            class="pl-button"
                            href="{{ route('saved-meals.index', ['date' => $date]) }}"
                        >
                            + Choose saved meal
                        </a>
                    </div>
                @endif
            </section>
        @empty
            <div class="mp-card">
                Choose your meals per day in
                <a href="{{ route('nutrition-profile.edit') }}">
                    Nutrition profile
                </a>
                to create your daily slots.
            </div>
        @endforelse
    </div>

    <p class="pl-footnote">
        These totals describe planned food, not food already eaten.
        Meal quantities stay as saved.
        Nutrition reflects the saved ingredient values; later ingredient
        edits do not rewrite these figures.
        Ingredient total carbohydrate includes fibre and differs from
        the calculator’s carbohydrate allocation.
    </p>
</div>
@endsection