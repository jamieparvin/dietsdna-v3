@extends('layouts.app')
@section('title', 'Generate my week')
@section('content')
    @if(!$setup['complete'] || request()->routeIs('weekly-meals.create'))
        @include('onboarding-progress', ['showSetupGuidance' => false])
    @endif
@include('weekly-meals.styles')
@php
    $calendarMonday = \Carbon\CarbonImmutable::now('Europe/London')->startOfWeek(1)->startOfDay();
    $selectedMonday = \Carbon\CarbonImmutable::instance($start)->startOfWeek(1)->startOfDay();
    $previousStart = old('start_date');
    if (is_string($previousStart) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $previousStart)) {
        try {
            $previousDate = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $previousStart, 'Europe/London');
            if ($previousDate && $previousDate->format('Y-m-d') === $previousStart) {
                $selectedMonday = $previousDate->startOfWeek(1);
            }
        } catch (\Throwable $exception) {
            // Keep the original valid week; the existing validation message is shown below.
        }
    }
    $weekOptions = collect(range(0, 4))->mapWithKeys(function ($offset) use ($calendarMonday) {
        $monday = $calendarMonday->addWeeks($offset);
        return [$monday->toDateString() => $monday];
    });
    if (! $weekOptions->has($selectedMonday->toDateString())) {
        $selectedMonday = $calendarMonday;
    }
    $weekOptions = $weekOptions->sortKeys();
    $weeklyHistory = $recent
        ->whereIn('status', ['queued', 'generating', 'ready', 'confirmed'])
        ->sortByDesc('id')
        ->unique(fn ($item) => $item->start_date->format('Y-m-d'))
        ->sortBy(fn ($item) => $item->start_date->format('Y-m-d'))
        ->values();
    $historyWeekStarts = $weeklyHistory
        ->map(fn ($item) => \Carbon\CarbonImmutable::instance($item->start_date)->startOfWeek(1)->startOfDay())
        ->values();
    $countWeekStarts = $historyWeekStarts->concat($weekOptions->values())
        ->unique(fn ($monday) => $monday->toDateString())->values();
    $historyWeekCounts = collect();
    if ($countWeekStarts->isNotEmpty()) {
        $historyWeekCounts = \App\Models\MealPlanEntry::query()
            ->where('user_id', auth()->id())
            ->where(function ($query) use ($countWeekStarts) {
                foreach ($countWeekStarts as $historyStart) {
                    $query->orWhere(function ($range) use ($historyStart) {
                        $range->where('plan_date', '>=', $historyStart->toDateString())
                            ->where('plan_date', '<', $historyStart->addWeek()->toDateString());
                    });
                }
            })
            ->get(['plan_date'])
            ->countBy(fn ($entry) => \Carbon\CarbonImmutable::instance($entry->plan_date)->startOfWeek(1)->toDateString());
    }
    $selectedHasMeals = (int) $historyWeekCounts->get($selectedMonday->toDateString(), 0) > 0;
    $selectedChoice = $selectedHasMeals && old('plan_choice') === 'fill' ? 'fill' : 'new';
    $initialMode = $selectedChoice === 'fill' || ! $selectedHasMeals ? 'fill' : 'replace';
    $historyLabels = ['queued' => 'Waiting to start', 'generating' => 'Generating meals', 'ready' => 'Ready to review'];
@endphp
<style>
    .wm-page .wm-button { min-height:44px; }
    .ww-modes { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:18px 0 24px; }
    .wm-page .ww-mode { display:flex; gap:12px; padding:18px; border:1px solid #dce6de; border-radius:12px; cursor:pointer; }
    .ww-mode input { width:20px!important; height:20px; flex:none; margin-top:3px; accent-color:#214e3d; }
    .ww-mode.is-disabled { opacity:.55; cursor:not-allowed; }
    .ww-mode:has(input:checked) { background:#edf3eb; border-color:#214e3d; }
    .ww-mode strong,.ww-mode span { display:block; }
    .ww-mode span { margin-top:6px; line-height:1.6; font-size:13px; }
    .ww-week-label { display:block; font-size:14px; font-weight:700; margin-bottom:10px; }
    .ww-week-control { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:stretch; max-width:760px; }
    .wm-page .ww-week-control select { width:100%; min-width:0; min-height:48px; padding:12px 14px; border:1px solid #ccdacf; border-radius:9px; background:#fff; color:#183d32; font:inherit; box-sizing:border-box; }
    .ww-week-control .wm-button { min-height:48px; border-radius:9px; }
    .ww-week-note { margin:10px 0 22px; font-size:12px; color:#6b7d71; }
    .ww-description { margin:18px 0 0; line-height:1.7; color:#61756a; font-size:13px; }
    .ww-week-control select:focus-visible,.ww-mode:focus-within { outline:3px solid #bc9148; outline-offset:3px; }
    .ww-history { margin:18px 0 0; padding:0; list-style:none; }
    .ww-history-item { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:17px 0; border-top:1px solid #edf1eb; }
    .ww-history-title { display:block; font-size:14px; font-weight:700; color:#254e39; margin-bottom:7px; }
    .ww-history-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:12px; color:#6b7d71; }
    .ww-history-status { padding:4px 8px; background:#edf4ed; color:#44664e; border-radius:16px; }
    .ww-history-link { display:inline-flex; align-items:center; min-height:44px; white-space:nowrap; font-size:13px; font-weight:700; color:#315c43; text-underline-offset:3px; }
    @media(max-width:640px) { .ww-modes { grid-template-columns:1fr; } .ww-week-control { grid-template-columns:1fr; } .ww-week-control .wm-button { width:100%; } .ww-history-item { align-items:flex-start; gap:10px; } .ww-history-title { font-size:13px; } }
</style>
<div class="wm-page">
    <h1>Generate my week</h1>
    <p class="wm-muted">Choose a week and build meals around your food preferences and nutrition goals.</p>
    <div class="wm-toolbar"><a href="{{ route('meal-plan.index', ['date' => $start->toDateString()]) }}">Back to meal plan</a></div>
    @include('partials.food-allergy-notice')
    @if($blocked)
        <section class="wm-panel" aria-labelledby="generation-blocked-heading">
            <h2 id="generation-blocked-heading">Meal generation unavailable</h2>
            <p role="status">{{ $blocked }}</p>
            <p>Your setup is complete. Automatic meal generation is currently unavailable. Keep your answers and food choices accurate; finishing setup does not mean meals can always be generated.</p>
            <a href="{{ route('nutrition-profile.edit') }}">View nutrition profile</a>
        </section>
    @else
        @if(!empty($source['context']['variety_warnings']))
            <p class="wm-notice" role="status">The food library needs a variety review for: {{ implode(', ', $source['context']['variety_warnings']) }}. These foods remain available, but suggestions may offer less protein variety.</p>
        @endif
        <section class="wm-panel">
            <h2>Build your week</h2>
            @if($source['context']['cooking_minutes'] ?? null)
                <p class="wm-muted">Time available per meal: {{ $source['context']['cooking_minutes'] }} minutes including preparation and cooking. We prefer meals within this time and offer the closest suitable alternatives when needed. Suggested times are AI estimates, not verified limits.</p>
            @endif
            <p class="wm-muted">
                {{ $source['context']['meal_count'] }} full meals
                @if(($source['context']['snack_count'] ?? 0) > 0)
                    and {{ $source['context']['snack_count'] }} optional {{ $source['context']['snack_count'] === 1 ? 'snack' : 'snacks' }}
                @else
                    and no optional snacks
                @endif
                per day: {{ 7 * count($source['slots']) }} slots across the week.
            </p>
            <form method="POST" action="{{ route('weekly-meals.store') }}" id="weekly-start-form">
                @csrf
                <input type="hidden" name="mode" id="weekly-generation-mode" value="{{ $initialMode }}">
                <fieldset style="border:0;padding:0;margin:0">
                    <legend>How would you like to build this week?</legend>
                    <div class="ww-modes">
                        <label class="ww-mode">
                            <input type="radio" name="plan_choice" id="weekly-choice-new" value="new" @checked($selectedChoice === 'new') required>
                            <div><strong>Generate new week</strong><span class="wm-muted">Create a fresh set of meals. If this week already has a plan, it stays in place until you approve the new week.</span></div>
                        </label>
                        <label class="ww-mode {{ !$selectedHasMeals ? 'is-disabled' : '' }}" id="weekly-fill-label">
                            <input type="radio" name="plan_choice" id="weekly-choice-fill" value="fill"
                                @checked($selectedChoice === 'fill') disabled
                                aria-describedby="weekly-fill-description" required>
                            <div><strong>Fill empty slots</strong><span class="wm-muted" id="weekly-fill-description">{{ $selectedHasMeals ? 'Keep meals already in your plan and add suggestions for the gaps.' : 'Available when the selected week already contains planned meals.' }}</span></div>
                        </label>
                    </div>
                </fieldset>
                <label for="weekly-start" class="ww-week-label">Choose your week</label>
                <div class="ww-week-control">
                    <select id="weekly-start" name="start_date" aria-describedby="weekly-week-note" required>
                        @foreach($weekOptions as $weekValue => $week)
                            <option value="{{ $weekValue }}" data-meal-count="{{ (int) $historyWeekCounts->get($weekValue, 0) }}" @selected($weekValue === $selectedMonday->toDateString())>
                                {{ $week->format($week->year !== $week->addDays(6)->year ? 'j M Y' : 'j M') }} – {{ $week->addDays(6)->format('j M Y') }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="wm-button" id="weekly-start-button">Generate weekly preview</button>
                </div>
                <p class="ww-week-note" id="weekly-week-note">Each plan covers a full week, Monday to Sunday.</p>
                @include('partials.nutrition-estimate-disclaimer')
            </form>
            <p class="ww-description">Review the meals before adding them to your plan. You can then swap any meals you don’t like with your saved options.</p>
            <p class="ww-description">Your meals are prepared in the background and may take a few minutes. Your current plan stays in place until you approve the preview.</p>
        </section>
    @endif
    <section class="wm-panel">
        <h2>Your generated weeks</h2>
        @if($weeklyHistory->isNotEmpty())
            <p class="wm-muted">Review your latest suggestions or open a week’s current meal plan. Counts reflect the meals currently planned.</p>
            <ul class="ww-history">
                @foreach($weeklyHistory as $item)
                    @php
                        $historyStartDate = \Carbon\CarbonImmutable::instance($item->start_date)->startOfWeek(1)->toDateString();
                        $currentMealCount = (int) $historyWeekCounts->get($historyStartDate, 0);
                    @endphp
                    <li class="ww-history-item">
                        <div>
                            <span class="ww-history-title">{{ $item->start_date->format('j M') }} – {{ $item->start_date->copy()->addDays(6)->format('j M Y') }}</span>
                            <div class="ww-history-meta">
                                @if($item->status === 'confirmed')
                                    <span class="ww-history-status">{{ $currentMealCount > 0 ? $currentMealCount.' '.($currentMealCount === 1 ? 'meal planned' : 'meals planned') : 'No meals planned' }}</span>
                                @else
                                    <span class="ww-history-status">{{ $historyLabels[$item->status] ?? ucfirst($item->status) }}</span>
                                    <span>{{ $item->mode === 'replace' ? 'New week' : 'Fill empty slots' }}</span>
                                @endif
                            </div>
                        </div>
                        @if($item->status === 'confirmed' && $currentMealCount === 0)
                            <a class="ww-history-link" href="{{ route('weekly-meals.create', ['date' => $historyStartDate, 'mode' => 'fill']) }}">Generate week →</a>
                        @elseif($item->status === 'confirmed')
                            <a class="ww-history-link" href="{{ route('meal-plan.index', ['date' => $historyStartDate]) }}">Open plan →</a>
                        @else
                            <a class="ww-history-link" href="{{ route('weekly-meals.show', $item->id) }}">{{ $item->status === 'ready' ? 'Review week' : 'View progress' }} →</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="wm-muted">Your generated weeks will appear here. Choose a week above to get started.</p>
        @endif
    </section>
</div>
<script>
(() => {
    const form = document.getElementById('weekly-start-form');
    if (!form) return;
    const week = document.getElementById('weekly-start');
    const newChoice = document.getElementById('weekly-choice-new');
    const fillChoice = document.getElementById('weekly-choice-fill');
    const mode = document.getElementById('weekly-generation-mode');
    const fillLabel = document.getElementById('weekly-fill-label');
    const description = document.getElementById('weekly-fill-description');

    function updateChoices(reset = false) {
        const count = Number(week.selectedOptions[0]?.dataset.mealCount || 0);
        const hasMeals = count > 0;
        fillChoice.disabled = !hasMeals;
        fillLabel.classList.toggle('is-disabled', !hasMeals);
        description.textContent = hasMeals
            ? 'Keep meals already in your plan and add suggestions for the gaps.'
            : 'Available when the selected week already contains planned meals.';
        if (reset || !hasMeals) {
            newChoice.checked = true;
            fillChoice.checked = false;
        }
        // Retain the existing server modes. A new empty week uses fill;
        // a new populated week uses the existing guarded replacement flow.
        mode.value = hasMeals && newChoice.checked ? 'replace' : 'fill';
    }

    week.addEventListener('change', () => updateChoices(true));
    newChoice.addEventListener('change', () => updateChoices());
    fillChoice.addEventListener('change', () => updateChoices());
    updateChoices();
    window.addEventListener('pageshow', () => updateChoices(true));
    form.addEventListener('submit', () => {
        updateChoices();
        const button = document.getElementById('weekly-start-button');
        button.disabled = true;
        button.textContent = 'Creating your week…';
    });
})();
</script>
@endsection
