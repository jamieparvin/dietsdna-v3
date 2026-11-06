@extends('layouts.app')
@section('title', 'Your dashboard')
@section('content')
@php
    $goalLabels = ['eat_better' => 'Eat better', 'lose_weight' => 'Lose weight', 'maintain_weight' => 'Maintain weight', 'gain_weight' => 'Gain weight', 'build_muscle' => 'Build muscle'];
    $firstName = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ');
    $hour = now('Europe/London')->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $ready = ($targets['status'] ?? null) === 'ready';
    $format = fn ($value, $decimals = 1) => \App\Services\Nutrition\SnapshotNutrition::format($value, $decimals);

    $dashboardMetrics = $metrics;
    $showNextWeek = request()->query('dashboard_week') === 'next';
    $displayWeekStart = \Carbon\CarbonImmutable::instance($weekStart);
    $displayWeekDays = $weekDays;
    $displayWeekPlannedDays = $weekPlannedDays;

    if ($showNextWeek) {
        $displayWeekStart = $displayWeekStart->addWeek();
        $nextWeekCounts = \App\Models\MealPlanEntry::query()
            ->where('user_id', auth()->id())
            ->where('plan_date', '>=', $displayWeekStart->toDateString())
            ->where('plan_date', '<', $displayWeekStart->addWeek()->toDateString())
            ->get(['plan_date'])
            ->countBy(fn ($entry) => $entry->plan_date->format('Y-m-d'));

        $displayWeekDays = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $dayDate = $displayWeekStart->addDays($offset);
            $displayWeekDays[] = [
                'date' => $dayDate,
                'count' => (int) $nextWeekCounts->get($dayDate->toDateString(), 0),
                'today' => $dayDate->toDateString() === $date,
            ];
        }
        $displayWeekPlannedDays = collect($displayWeekDays)->where('count', '>', 0)->count();
    }

    $dailyPatternText = 'Not set';
    if ($patternCount > 0) {
        $dailyPatternText = $profile->meals_per_day.' full meals';
        $optionalSnacks = (int) ($profile->snacks_per_day ?? 0);
        if ($optionalSnacks > 0) {
            $dailyPatternText .= ', '.$optionalSnacks.' optional '.($optionalSnacks === 1 ? 'snack' : 'snacks');
        }
    }

@endphp
<style>
.dn-home {width:100%;min-width:0;color:#173e31;--dn-line:#dfe7df;--dn-muted:#61756a;}
.dn-home * {box-sizing:border-box;}
.dn-home h1,.dn-home h2,.dn-home h3,.dn-home p {margin-top:0;}
.dn-home a {text-underline-offset:3px;}
.dn-home a:focus-visible,.dn-home summary:focus-visible {outline:3px solid #b79044;outline-offset:4px;}
.dn-hero {position:relative;overflow:hidden;background:linear-gradient(115deg,#153f31,#237450);border-radius:22px;padding:32px 36px;display:flex;align-items:center;justify-content:space-between;gap:30px;color:#fff;}
.dn-hero::after {content:'';position:absolute;width:260px;height:260px;right:-110px;top:-145px;border:42px solid #ffffff08;border-radius:50%;pointer-events:none;}
.dn-eyebrow {font-size:11px;font-weight:750;letter-spacing:1.7px;text-transform:uppercase;}
.dn-hero .dn-eyebrow {color:#c4ddcb;margin-bottom:12px;}
.dn-hero h1 {font-size:clamp(27px,3vw,39px);letter-spacing:-1px;line-height:1.18;margin-bottom:12px;overflow-wrap:anywhere;}
.dn-hero p {color:#deebdf;margin-bottom:22px;line-height:1.65;max-width:560px;}
.dn-actions {display:flex;gap:10px;flex-wrap:wrap;}
.dn-button {min-height:44px;padding:12px 17px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:9px;font-size:13px;font-weight:750;text-decoration:none;line-height:1.4;color:#fff;background:#1c4936;border:1px solid #1c4936;}
.dn-button-light {background:#f6f2e6;color:#173e31;border-color:#f6f2e6;}
.dn-button-ghost {background:#ffffff08;border-color:#ffffff50;color:#fff;}
.dn-button-outline {background:#fff;color:#1c4936;border-color:#d7e1d8;}
.dn-hero-summary {flex:0 0 174px;position:relative;z-index:1;border:1px solid #ffffff30;background:#ffffff08;padding:20px;border-radius:16px;text-align:center;}
.dn-hero-summary strong {display:block;font-size:39px;line-height:1.15;font-weight:600;letter-spacing:-1px;}
.dn-hero-summary span {display:block;margin:7px 0 17px;color:#d9e8dc;font-size:12px;}
.dn-hero-summary time {display:block;border-top:1px solid #ffffff22;padding-top:13px;font-size:12px;color:#e7eee6;}
.dn-notice {margin-top:20px;border:1px solid #e5dec9;background:#fcf8ee;padding:18px 21px;border-radius:13px;display:flex;justify-content:space-between;align-items:center;gap:18px;}
.dn-notice h2 {font-size:16px;margin-bottom:6px;}.dn-notice p {font-size:13px;color:#756951;margin-bottom:0;line-height:1.6;}
.dn-heading {display:flex;align-items:center;justify-content:space-between;gap:15px;margin:27px 0 15px;flex-wrap:wrap;}
.dn-heading h2 {font-size:20px;letter-spacing:-.4px;margin:0;}.dn-muted {color:var(--dn-muted);font-size:13px;line-height:1.65;}
.dn-heading a,.dn-panel-head a {display:inline-flex;align-items:center;min-height:44px;font-size:13px;color:#285740;font-weight:700;}
.dn-metrics {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;}
.dn-metric {background:#fff;border:1px solid var(--dn-line);border-radius:15px;padding:20px;min-width:0;}
.dn-metric-title {display:flex;align-items:center;gap:8px;font-size:13px;font-weight:650;color:#50665a;}
.dn-dot {height:8px;width:8px;border-radius:50%;background:var(--dn-color);flex-shrink:0;}
.dn-guide {display:block;font-size:clamp(23px,2.2vw,30px);margin:14px 0 2px;letter-spacing:-.6px;line-height:1.3;}.dn-guide small {font-size:13px;font-weight:500;letter-spacing:0;}
.dn-caption {font-size:11px;color:var(--dn-muted);}.dn-planned {margin:15px 0 8px;font-size:12px;color:#4f6558;}.dn-planned strong {color:#254937;}
.dn-track {height:5px;background:#edf2ec;border-radius:10px;overflow:hidden;}.dn-track span {display:block;height:100%;background:var(--dn-color);border-radius:10px;}
.dn-comparison {font-size:11px;line-height:1.5;color:var(--dn-muted);margin:9px 0 0;}
.dn-nutrition-note {font-size:12px;color:var(--dn-muted);margin:12px 0 0;line-height:1.7;}.dn-nutrition-note summary {cursor:pointer;display:list-item;width:fit-content;color:#365f46;font-weight:650;}.dn-nutrition-note p {margin:8px 0 0;max-width:900px;}
.dn-main {display:grid;grid-template-columns:minmax(0,1.65fr) minmax(285px,1fr);gap:20px;margin-top:24px;align-items:start;}
.dn-panel {background:#fff;border:1px solid var(--dn-line);border-radius:17px;padding:24px;min-width:0;}
.dn-panel-head {display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap;}.dn-panel-head h2 {font-size:19px;letter-spacing:-.3px;margin:0;}
.dn-badge {display:inline-block;padding:5px 9px;border-radius:20px;background:#edf4ed;font-size:11px;color:#44664e;line-height:1.5;}
.dn-menu-list {list-style:none;margin:0;padding:0;}
.dn-menu-row {display:grid;grid-template-columns:34px minmax(0,1fr);gap:13px;padding:18px 0;border-top:1px solid #edf1eb;}
.dn-order {display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;background:#edf4ed;color:#39634a;font-size:12px;font-weight:750;}
.dn-menu-top {display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:5px;}.dn-slot {font-size:11px;color:#6c7d6e;font-weight:700;letter-spacing:.3px;}.dn-meal-energy {white-space:nowrap;font-size:12px;font-weight:700;color:#335d43;}
.dn-menu-row h3 {font-size:15px;line-height:1.5;margin-bottom:7px;overflow-wrap:anywhere;}.dn-meal-macros {display:flex;gap:5px 13px;flex-wrap:wrap;font-size:11px;color:#6b7b6b;margin-bottom:0;}
.dn-menu-row details {font-size:12px;margin-top:12px;line-height:1.7;}.dn-menu-row summary {cursor:pointer;color:#315e43;font-weight:650;min-height:44px;}.dn-menu-row ul {padding-left:18px;margin:9px 0;}.dn-method {white-space:pre-line;color:#5b7060;margin:12px 0 4px;}
.dn-empty {padding:18px;background:#f5f8f2;border-radius:11px;}.dn-empty h3 {font-size:16px;margin-bottom:8px;}.dn-empty p {margin-bottom:14px;}.dn-empty-row h3 {color:#829184;font-size:13px;font-weight:500;margin:4px 0 7px;}.dn-empty-row a {font-size:12px;font-weight:650;color:#325d44;display:inline-flex;align-items:center;min-height:44px;}
.dn-side {display:grid;gap:20px;min-width:0;}
.dn-week {display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px;}
.dn-day {min-height:83px;border:1px solid #e7ece5;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-direction:column;color:#516d58;text-decoration:none;gap:7px;padding:10px 1px;font-size:10px;}
.dn-day strong {font-size:16px;font-weight:650;}.dn-day.is-today {border-color:#8fad95;background:#f0f6ed;color:#254e37;}.dn-day-mark {width:5px;height:5px;border-radius:50%;background:#dfe6dc;}.dn-day.has-meals .dn-day-mark {background:#43835a;}
.dn-week-footer {display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:14px;}.dn-week-footer p {margin:0;font-size:11px;color:var(--dn-muted);}.dn-week-footer a {font-size:12px;font-weight:700;color:#325d44;min-height:44px;display:inline-flex;align-items:center;}
.dn-goal {padding:17px;background:#f5f7ef;border-radius:11px;margin-bottom:17px;}.dn-goal .dn-eyebrow {color:#768167;font-size:10px;}.dn-goal h3 {font-size:19px;margin:9px 0 0;}
.dn-profile {margin:0;}.dn-profile div {display:flex;justify-content:space-between;align-items:baseline;gap:18px;padding:11px 0;border-bottom:1px solid #edf1eb;font-size:12px;}.dn-profile dt {color:var(--dn-muted);}.dn-profile dd {margin:0;text-align:right;font-weight:650;overflow-wrap:anywhere;}.dn-profile-link {font-size:12px;color:#315c43;font-weight:700;display:inline-flex;align-items:center;min-height:44px;margin-top:8px;}
.dn-shortcuts {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;}
.dn-shortcut {padding:21px;border:1px solid var(--dn-line);border-radius:14px;background:#fff;color:#173e31;text-decoration:none;display:flex;flex-direction:column;gap:10px;transition:border-color .15s;min-width:0;}.dn-shortcut:hover {border-color:#91b198;}.dn-shortcut-top {display:flex;justify-content:space-between;gap:10px;align-items:center;}.dn-shortcut h3 {font-size:15px;margin:0;}.dn-shortcut p {font-size:12px;color:var(--dn-muted);line-height:1.7;margin:0;}.dn-shortcut-arrow {font-size:20px;color:#68866c;}.dn-footer {font-size:11px;line-height:1.7;color:var(--dn-muted);margin:20px 0 0;}
@media(max-width:1150px) {.dn-main {grid-template-columns:minmax(0,1.4fr) minmax(275px,1fr);}.dn-panel {padding:20px;}.dn-metric {padding:17px;}.dn-hero {padding:28px;}}
@media(max-width:950px) {.dn-main {grid-template-columns:1fr;}.dn-side {grid-template-columns:repeat(2,minmax(0,1fr));}.dn-metrics {grid-template-columns:repeat(2,minmax(0,1fr));}.dn-hero-summary {flex-basis:155px;}}
@media(max-width:600px) {.dn-hero {padding:24px 20px;border-radius:17px;flex-direction:column;align-items:stretch;gap:20px;}.dn-hero p {font-size:13px;margin-bottom:19px;}.dn-hero-summary {flex:auto;display:flex;align-items:center;gap:9px;padding:13px 15px;text-align:left;}.dn-hero-summary strong {font-size:24px;white-space:nowrap;}.dn-hero-summary span {margin:0;font-size:11px;max-width:80px;line-height:1.5;}.dn-hero-summary time {margin-left:auto;padding:0 0 0 12px;border-top:0;border-left:1px solid #ffffff22;font-size:11px;max-width:105px;line-height:1.5;}.dn-actions .dn-button {flex:1;font-size:12px;padding:12px 8px;}.dn-metrics {gap:10px;}.dn-metric {padding:16px 13px;}.dn-metric-title {font-size:12px;}.dn-guide {font-size:25px;}.dn-heading {margin-top:23px;}.dn-heading h2 {font-size:19px;}.dn-notice {align-items:stretch;flex-direction:column;gap:12px;padding:17px;}.dn-notice .dn-button {align-self:flex-start;}.dn-main {gap:16px;margin-top:20px;}.dn-panel {padding:19px 16px;}.dn-side {grid-template-columns:1fr;gap:16px;}.dn-shortcuts {grid-template-columns:1fr;gap:10px;}.dn-shortcut {padding:18px;}.dn-menu-row {gap:10px;grid-template-columns:30px minmax(0,1fr);}.dn-order {width:28px;height:28px;}.dn-menu-row h3 {font-size:14px;}.dn-meal-macros {gap:5px 9px;}}

#dashboard-week {scroll-margin-top:24px;}
.dn-week-switch {display:flex;gap:5px;padding:4px;border-radius:10px;background:#f2f5ef;margin-bottom:16px;}
.dn-week-tab {display:flex;flex:1;align-items:center;justify-content:center;min-height:44px;padding:9px 12px;border-radius:7px;font-size:12px;font-weight:700;color:#5d7461;text-decoration:none;}
.dn-week-tab.is-active {background:#fff;color:#214e36;box-shadow:0 1px 4px #173e3110;}
.dn-profile div.dn-pattern-row {flex-wrap:wrap;gap:6px 14px;}
.dn-profile .dn-pattern-row dd {white-space:nowrap;margin-left:auto;}

.dn-progress-measures {display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,140px),1fr));gap:16px;align-items:stretch;}
.dn-progress-measures .dn-goal {min-width:0;}
.dn-progress-measures .dn-eyebrow {display:block;min-height:3em;}
@media(max-width:600px) {.dn-progress-measures {grid-template-columns:1fr;}}
</style>
<div class="dn-home">
    <section class="dn-hero" aria-labelledby="dn-welcome">
        <div>
            <div class="dn-eyebrow">Your daily space</div>
            <h1 id="dn-welcome">{{ $greeting }}, {{ $firstName }}.</h1>
            <p>Your meals and nutrition, together in one place. See what’s planned and make the day your own.</p>
            <div class="dn-actions">
                <a class="dn-button dn-button-light" href="{{ route('meal-plan.index', ['date' => $date]) }}">View today’s plan <span aria-hidden="true">→</span>
</a>
                <a class="dn-button dn-button-ghost" href="{{ route('meal-ideas.index') }}">Find meal ideas</a>
            </div>
        </div>
        <div class="dn-hero-summary">
            <strong>
@if($patternCount > 0)
{{ $currentFilled }}<span style="display:inline;font-size:20px;color:#bfdbc7;margin:0"> / {{ $patternCount }}</span>
@else
 — 
@endif
</strong>
            <span>{{ $patternCount > 0 ? 'Today’s slots planned' : 'Set your meal pattern' }}</span>
            <time datetime="{{ $date }}">{{ $today->format('l, j F') }}</time>
        </div>
    </section>
    
@if(!$ready)

        <section class="dn-notice" aria-labelledby="dn-setup-title">
            <div>
<h2 id="dn-setup-title">{{ ($targets['status'] ?? '') === 'review' ? 'Automatic planning unavailable' : 'Set your daily nutrition guide' }}</h2>
<p>{{ $targets['message'] }}</p>
</div>
            <a class="dn-button dn-button-outline" href="{{ route('nutrition-profile.edit') }}">{{ $profile ? 'Review profile' : 'Set up profile' }}</a>
        </section>
    
@endif

    <div class="dn-heading">
<h2>Your daily nutrition</h2>
<a href="{{ route('nutrition-profile.edit') }}">View calculations &amp; adjust macros →</a>
</div>
    @include('partials.nutrition-estimate-disclaimer')
    <section class="dn-metrics" aria-label="Daily targets and planned nutrition">
        
@foreach($dashboardMetrics as $metric)

            <article class="dn-metric" style="--dn-color:{{ $metric['color'] }}">
                <div class="dn-metric-title">
<span class="dn-dot" aria-hidden="true">
</span>{{ $metric['label'] }}</div>


                <strong class="dn-guide">{{ $format($metric['guide'], $metric['decimals']) }} <small>{{ $metric['unit'] }}</small>
</strong>
                <span class="dn-caption">{{ $metric['guide'] === null ? 'Guide unavailable' : 'Daily guide' }}</span>
                <p class="dn-planned">
<strong>{{ $format($metric['planned'], $metric['decimals']) }} {{ $metric['unit'] }}</strong> planned today</p>
                @if($metric['percent'] !== null)
                <div class="dn-track" aria-hidden="true">
<span style="width:{{ $metric['percent'] === null ? 0 : min(100, max(0, $metric['percent'])) }}%">
</span>
</div>
                @endif
                <p class="dn-comparison">
                    
@if($metric['planned'] === null)

                        Some planned nutrition is unavailable.
                    
@elseif($metric['guide'] === null)

                        Review your profile for a guide.
                    
@elseif($plannedCount === 0)

                        Add meals to build your day.
                    
@else

                        {{ $format(abs($metric['planned'] - $metric['guide']), $metric['decimals']) }} {{ $metric['unit'] }} {{ $metric['planned'] > $metric['guide'] ? 'above' : 'below' }} guide
                    
@endif

                </p>

            </article>
        
@endforeach

    </section>
    <details class="dn-nutrition-note">
<summary>About these figures</summary>
<p>These are planned amounts, not a record of food eaten. Your guide uses your current nutrition profile and macro settings. Planned nutrition comes from each meal’s saved snapshot. Available carbohydrate is estimated as total carbohydrate minus fibre; meal cards below show total carbohydrate. A dash means a figure is unavailable.</p>
</details>
    <div class="dn-main">
        <section class="dn-panel" aria-labelledby="dn-today-title">
            <div class="dn-panel-head">
<h2 id="dn-today-title">Today’s menu</h2>
<span class="dn-badge">{{ $plannedCount }} {{ $plannedCount === 1 ? 'meal' : 'meals' }} planned</span>
</div>
            
@if($plannedCount === 0)

                <div class="dn-empty">
<h3>Your next meal starts here</h3>
<p class="dn-muted">Choose a saved favourite or generate a week using your food preferences.</p>
<div class="dn-actions">
<a class="dn-button" href="{{ route('weekly-meals.create', ['date' => $date]) }}">Generate my week</a>
<a class="dn-button dn-button-outline" href="{{ route('saved-meals.index', ['date' => $date]) }}">Choose saved meals</a>
</div>
</div>
            
@endif

            <ol class="dn-menu-list">
                
@foreach($rows as $row)

                    <li class="dn-menu-row {{ $row['entry'] ? '' : 'dn-empty-row' }}">
                        <div class="dn-order" aria-hidden="true">{{ $loop->iteration }}</div>
                        <div>
                            <div class="dn-menu-top">
<span class="dn-slot">{{ $row['label'] }}</span>
@if($row['entry'])
<span class="dn-meal-energy">{{ $format($row['nutrition']['energy_kcal'], 0) }} kcal</span>
@endif
</div>
                            
@if($row['entry'])

                                <h3>{{ $row['snapshot']['name'] ?? 'Planned meal' }}</h3>
                                <p class="dn-meal-macros">
<span>{{ $format($row['nutrition']['protein_g']) }} g protein</span>
<span>{{ $format($row['nutrition']['carbohydrate_g']) }} g total carbs</span>
<span>{{ $format($row['nutrition']['fat_g']) }} g fat</span>
<span>{{ $format($row['nutrition']['fibre_g']) }} g fibre</span>
</p>
                                <details>
<summary>Ingredients &amp; preparation</summary>
<ul>
@foreach(($row['snapshot']['ingredients'] ?? []) as $ingredient)
<li>{{ $ingredient['name'] ?? 'Ingredient' }} — {{ $format($ingredient['quantity_g'] ?? null) }} g 
@if(!empty($ingredient['preparation_state']))
({{ $ingredient['preparation_state'] }})
@endif
</li>
@endforeach
</ul>
@if(!empty($row['snapshot']['instructions']))
<div class="dn-method">{{ $row['snapshot']['instructions'] }}</div>
@endif
<a href="{{ route('meal-plan.index', ['date' => $date]) }}">Manage this meal in your plan →</a>
</details>
                            
@else

                                <h3>No meal selected yet</h3>
<a href="{{ route('saved-meals.index', ['date' => $date]) }}">Choose a saved meal →</a>
                            
@endif

                        </div>
                    </li>
                
@endforeach

            </ol>
            
@if($patternCount === 0)
<p class="dn-muted" style="margin:16px 0 0">Choose your meals per day in your <a href="{{ route('nutrition-profile.edit') }}">nutrition profile</a> to set your daily slots.</p>
@endif

            <a class="dn-profile-link" href="{{ route('meal-plan.index', ['date' => $date]) }}">Open today’s meal plan →</a>
        </section>
        <div class="dn-side">
            <section class="dn-panel" id="dashboard-week" aria-labelledby="dn-week-title">
                <div class="dn-panel-head">
<h2 id="dn-week-title">Your week</h2>
<span class="dn-muted" style="font-size:11px">{{ $displayWeekStart->format('j M') }} – {{ $displayWeekStart->addDays(6)->format('j M Y') }}</span>
</div>
                
                <nav class="dn-week-switch" aria-label="Choose a planning week">
                    <a class="dn-week-tab {{ !$showNextWeek ? 'is-active' : '' }}"
                       href="{{ route('dashboard') }}#dashboard-week"
                       aria-label="Show this week"
                       aria-current="{{ !$showNextWeek ? 'true' : 'false' }}">This week</a>
                    <a class="dn-week-tab {{ $showNextWeek ? 'is-active' : '' }}"
                       href="{{ route('dashboard', ['dashboard_week' => 'next']) }}#dashboard-week"
                       aria-label="Show next week"
                       aria-current="{{ $showNextWeek ? 'true' : 'false' }}">Next week</a>
                </nav>

                <nav class="dn-week" aria-label="Displayed week’s meal plans">
                    
@foreach($displayWeekDays as $day)
<a class="dn-day {{ $day['today'] ? 'is-today' : '' }} {{ $day['count'] > 0 ? 'has-meals' : '' }}" href="{{ route('meal-plan.index', ['date' => $day['date']->toDateString()]) }}" aria-label="{{ $day['date']->format('l j F') }}: {{ $day['count'] }} meals planned" 
@if($day['today'])
aria-current="date"
@endif
>
<span>{{ $day['date']->format('D') }}</span>
<strong>{{ $day['date']->format('j') }}</strong>
<span class="dn-day-mark" aria-hidden="true">
</span>
</a>
@endforeach

                </nav>
                <div class="dn-week-footer">
<p>{{ $displayWeekPlannedDays }} of 7 days have planned meals</p>
<a href="{{ route('weekly-meals.create', ['date' => $displayWeekStart->toDateString()]) }}">Plan my week →</a>
</div>
            </section>
            <section class="dn-panel" aria-labelledby="dn-profile-title">
                <div class="dn-panel-head">
<h2 id="dn-profile-title">Your nutrition profile</h2>
<span class="dn-badge">{{ $ready ? 'Guide ready' : 'Needs attention' }}</span>
</div>
                <div class="dn-goal">
<span class="dn-eyebrow">Your main goal</span>
<h3>{{ $goalLabels[$profile?->goal ?? ''] ?? 'Choose your direction' }}</h3>
</div>
                <dl class="dn-profile">
                    <div class="dn-pattern-row">
                        <dt>Daily pattern</dt>
                        <dd>{{ $dailyPatternText }}</dd>
                    </div>
                    <div>
<dt>Profile weight</dt>
<dd>{{ $profile?->weight_kg === null ? 'Not set' : \App\Services\Nutrition\WeightUnits::format($profile->weight_kg).' lbs' }}</dd>
</div>
                    <div>
<dt>Ingredient choices</dt>
<dd><a href="{{ route('food-preferences.edit') }}">Food preferences</a></dd>
</div>
                    <div>
<dt>Macro settings</dt>
<dd>{{ !$ready ? 'Review profile' : (($targets['macro_source'] ?? 'goal') === 'custom' ? 'Your custom split' : 'Goal defaults') }}</dd>
</div>
                </dl>
                <a class="dn-profile-link" href="{{ route('nutrition-profile.edit') }}">Review profile &amp; targets →</a>
            </section>
                    @php
            $progressFirst = auth()->user()
                ->weightEntries()
                ->where('recorded_on', '<', $today->addDay()->toDateString())
                ->orderBy('recorded_on')
                ->orderBy('id')
                ->first();

            $progressLatest = auth()->user()
                ->weightEntries()
                ->where('recorded_on', '<', $today->addDay()->toDateString())
                ->orderByDesc('recorded_on')
                ->orderByDesc('id')
                ->first();

            $progressChange = $progressFirst && $progressLatest
                ? (float) $progressLatest->weight_kg
                    - (float) $progressFirst->weight_kg
                : null;

            $progressTarget = $profile?->target_weight_kg;
        @endphp

        <section class="dn-panel" aria-labelledby="dn-progress-title">
            <div class="dn-panel-head">
                <h2 id="dn-progress-title">Your progress</h2>

                <span class="dn-badge">
                    {{ $progressLatest ? 'Weight tracking' : 'Get started' }}
                </span>
            </div>

            <div class="dn-progress-measures">
                @if($progressLatest)
                    <div class="dn-goal">
                        <span class="dn-eyebrow">Latest recorded weight</span>

                        <h3 style="font-size:30px;margin-bottom:6px;">
                            {{ \App\Services\Nutrition\WeightUnits::format($progressLatest->weight_kg) }}
                            <small style="font-size:14px;font-weight:500;">lbs</small>
                        </h3>

                        <p class="dn-muted" style="margin:0;">
                            {{ $progressLatest->recorded_on->format('j F Y') }}
                        </p>
                    </div>
                @else
                    <div class="dn-goal">
                        <h3 style="margin-bottom:8px;">Start your weight history</h3>

                        <p class="dn-muted" style="margin:0;">
                            Record your first weigh-in to track changes towards your goal.
                        </p>
                    </div>
                @endif
                <div class="dn-goal" aria-label="Latest BMI">
                    <span class="dn-eyebrow">Latest BMI</span>
                    <h3 data-bmi-value style="font-size:30px;margin-bottom:6px;">{{ $bmi['value'] === null ? 'Not available' : number_format($bmi['value'], 1) }}</h3>
                    @if($bmi['value'] !== null && $bmi['date'])
                        <p class="dn-muted" style="margin:0;">
                            <time datetime="{{ $bmi['date'] }}">{{ \Carbon\CarbonImmutable::parse($bmi['date'])->format('j F Y') }}</time>
                        </p>
                    @endif
                </div>
            </div>
            @include('partials.bmi-context')

            @if($progressLatest)
                <dl class="dn-profile">
                    <div>
                        <dt>Starting weight</dt>
                        <dd>
                            {{ \App\Services\Nutrition\WeightUnits::format($progressFirst->weight_kg) }} lbs
                        </dd>
                    </div>

                    <div>
                        <dt>Change since first entry</dt>
                        <dd>
                            {{ $progressChange > 0 ? '+' : '' }}{{ \App\Services\Nutrition\WeightUnits::format($progressChange) }} lbs
                        </dd>
                    </div>

                    <div>
                        <dt>Target weight</dt>
                        <dd>
                            {{ $progressTarget === null
                                ? 'Not set'
                                : \App\Services\Nutrition\WeightUnits::format($progressTarget).' lbs' }}
                        </dd>
                    </div>
                </dl>

                <a class="dn-profile-link" href="{{ route('progress.index') }}">
                    View progress &amp; record weight →
                </a>
            @else
                <a
                    class="dn-profile-link"
                    href="{{ route('progress.index') }}#weight-entry-form"
                >
                    Record your first weight →
                </a>
            @endif

            <p class="dn-muted" style="font-size:11px;margin:14px 0 0;">
                Weigh-ins are separate from your profile weight and do not
                automatically change your calorie guide.
            </p>
        </section>
        </div>
    </div>
    <div class="dn-heading">
<h2>Make it your own</h2>
<span class="dn-muted">Your food choices, your routine</span>
</div>
    <nav class="dn-shortcuts" aria-label="Meal planning shortcuts">
        <a class="dn-shortcut" href="{{ route('meal-ideas.index') }}">
<div class="dn-shortcut-top">
<h3>Find meal ideas</h3>
<span class="dn-shortcut-arrow" aria-hidden="true">↗</span>
</div>
<p>Explore options checked against your meal’s calorie and macro guide.</p>
</a>
        <a class="dn-shortcut" href="{{ route('saved-meals.index', ['date' => $date]) }}">
<div class="dn-shortcut-top">
<h3>Your saved meals</h3>
<span class="dn-badge">{{ $savedCount }} saved</span>
</div>
<p>Keep your favourites ready to add to a day or swap into your plan.</p>
</a>
        <a class="dn-shortcut" href="{{ route('food-preferences.edit') }}">
<div class="dn-shortcut-top">
<h3>Your food preferences</h3>
<span class="dn-shortcut-arrow" aria-hidden="true">↗</span>
</div>
<p>Choose foods you like and dislike to shape future meal ideas.</p>
</a>
    </nav>
    <p class="dn-footer">Updating your profile changes your targets; meals already in your plan keep their saved portions.</p>
</div>
@endsection
