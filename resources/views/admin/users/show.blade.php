@extends('layouts.app')

@section('title', 'Customer details')

@section('content')
@php
    $format = fn ($value, $decimals = 1) =>
        \App\Services\Nutrition\SnapshotNutrition::format($value, $decimals);

    $goalLabels = [
        'eat_better' => 'Eat better',
        'lose_weight' => 'Lose weight',
        'maintain_weight' => 'Maintain weight',
        'gain_weight' => 'Gain weight',
        'build_muscle' => 'Build muscle',
    ];

    $activityLabels = [
        'sedentary' => 'Mostly seated',
        'light' => 'Light activity',
        'moderate' => 'Moderately active',
        'high' => 'Very active',
    ];

    $dietLabels = [
        'no_preference' => 'No particular preference',
        'vegetarian' => 'Vegetarian',
        'vegan' => 'Vegan',
        'pescatarian' => 'Pescatarian',
        'flexitarian' => 'Flexitarian',
    ];

    $draftLabels = [
        'queued' => 'Waiting to start',
        'generating' => 'Generating',
        'ready' => 'Ready for review',
        'confirmed' => 'Confirmed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    $ready = ($targets['status'] ?? null) === 'ready';

    $macroCards = [
        ['label' => 'Calories', 'key' => 'calories_kcal', 'unit' => 'kcal', 'decimals' => 0],
        ['label' => 'Protein', 'key' => 'protein_g', 'unit' => 'g', 'decimals' => 1],
        ['label' => 'Available carbs', 'key' => 'carbohydrate_g', 'unit' => 'g', 'decimals' => 1],
        ['label' => 'Fat', 'key' => 'fat_g', 'unit' => 'g', 'decimals' => 1],
    ];

    $preferenceSections = [
        ['label' => 'Liked foods', 'foods' => $likedFoods, 'class' => 'cu-liked'],
        ['label' => 'Excluded foods', 'foods' => $excludedFoods, 'class' => 'cu-excluded'],
    ];
@endphp

@include('meals.styles')

<style>
    .cu-page {width:100%;color:#183d32;}
    .cu-page * {box-sizing:border-box;}
    .cu-header,.cu-panel-head,.cu-actions {display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
    .cu-header {margin-bottom:24px;}
    .cu-eyebrow {margin:0 0 8px;color:#738575;font-size:11px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;}
    .cu-header h1 {margin:0 0 9px;font-size:30px;letter-spacing:-.6px;overflow-wrap:anywhere;}
    .cu-muted {margin:0;color:#6b7d71;font-size:12px;line-height:1.8;overflow-wrap:anywhere;}
    .cu-button {display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 15px;border:1px solid #d5e0d8;border-radius:9px;background:#fff;color:#234d37;font:inherit;font-size:13px;font-weight:650;text-decoration:none;cursor:pointer;}
    .cu-page a:focus-visible,.cu-page summary:focus-visible,.cu-page input:focus-visible {outline:3px solid #9fbea9;outline-offset:3px;}
    .cu-badge {display:inline-block;padding:5px 9px;border-radius:20px;background:#edf4ed;color:#44664e;font-size:11px;line-height:1.5;}
    .cu-notice {padding:15px 18px;margin-bottom:22px;border:1px solid #d9e5db;border-radius:11px;background:#edf4ee;font-size:13px;line-height:1.7;}
    .cu-error {background:#fff5f2;border-color:#eaccc3;color:#874b3c;}
    .cu-stats {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px;}
    .cu-stat,.cu-panel {min-width:0;padding:22px;border:1px solid #dfe7e1;border-radius:15px;background:#fff;}
    .cu-stat-label {font-size:12px;font-weight:650;color:#50665a;}
    .cu-value {display:block;margin:12px 0 7px;font-size:28px;line-height:1.3;letter-spacing:-.5px;}
    .cu-value small {font-size:13px;font-weight:500;}
    .cu-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-bottom:22px;align-items:start;}
    .cu-panel-head {margin-bottom:18px;}
    .cu-panel h2 {margin:0;font-size:19px;letter-spacing:-.3px;}
    .cu-panel h3 {margin:20px 0 10px;font-size:15px;}
    .cu-details {margin:0;}
    .cu-details > div {display:flex;justify-content:space-between;align-items:flex-start;gap:18px;padding:12px 0;border-top:1px solid #edf1eb;}
    .cu-details dt {color:#6b7d71;font-size:12px;}
    .cu-details dd {margin:0;text-align:right;font-size:13px;font-weight:650;overflow-wrap:anywhere;max-width:65%;}
    .cu-text {white-space:pre-line;color:#50665a;font-size:13px;line-height:1.8;overflow-wrap:anywhere;}
    .cu-chips {display:flex;flex-wrap:wrap;gap:7px;list-style:none;margin:0;padding:0;}
    .cu-chip {padding:7px 10px;border:1px solid #dfe7e1;border-radius:9px;font-size:12px;line-height:1.6;overflow-wrap:anywhere;}
    .cu-liked .cu-chip {background:#f2f8f3;border-color:#d4e5d8;}
    .cu-excluded .cu-chip {background:#fff7f4;border-color:#ecd7cf;}
    .cu-chip small {display:block;color:#758477;font-size:10px;}
    .cu-compact-list {list-style:none;margin:0;padding:0;}
    .cu-compact-list li {display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:12px 0;border-top:1px solid #edf1eb;font-size:13px;}
    .cu-compact-list strong {display:block;margin-bottom:4px;}
    .cu-empty {padding:17px;border-radius:10px;background:#f7f9f4;color:#6b7d71;font-size:13px;line-height:1.8;}
    .cu-week-panel {margin-bottom:22px;}
    .cu-week-form {display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0;}
    .cu-week-form label {font-size:12px;color:#6b7d71;}
    .cu-week-form input {min-height:44px;max-width:100%;padding:9px 11px;border:1px solid #cbd9ce;border-radius:8px;background:#fff;color:#183d32;font:inherit;font-size:13px;}
    .cu-days {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;margin-top:20px;align-items:start;}
    .cu-day {min-width:0;border:1px solid #e0e7e1;border-radius:12px;overflow:hidden;}
    .cu-day-head {padding:16px;background:#f7f9f4;border-bottom:1px solid #e8eee8;}
    .cu-day-head h3 {margin:0 0 7px;font-size:15px;}
    .cu-day-head p {margin:0;font-size:11px;color:#6b7d71;line-height:1.8;}
    .cu-meal {padding:16px;border-bottom:1px solid #edf1eb;}
    .cu-meal:last-child {border-bottom:0;}
    .cu-slot {display:block;margin-bottom:9px;font-size:11px;font-weight:700;color:#6b7d71;}
    .cu-meal h3 {font-size:15px;margin:0 0 8px;line-height:1.6;}
    .cu-meal .mp-nutrition {font-size:12px;}
    .cu-meal .mp-muted,.cu-meal details {font-size:12px;}
    .cu-note {margin:14px 0 0;color:#758477;font-size:11px;line-height:1.8;}
    @media(max-width:1050px) {
        .cu-stats {grid-template-columns:repeat(2,minmax(0,1fr));}
        .cu-grid {grid-template-columns:1fr;}
    }
    @media(max-width:700px) {
        .cu-days {grid-template-columns:1fr;}
    }
    @media(max-width:550px) {
        .cu-header h1 {font-size:25px;}
        .cu-stats {gap:10px;}
        .cu-stat {padding:16px 13px;}
        .cu-value {font-size:24px;}
        .cu-panel {padding:18px 15px;}
        .cu-week-form {width:100%;}
        .cu-week-form label {width:100%;}
        .cu-week-form input {flex:1;min-width:0;}
    }
</style>

<div class="cu-page">
    <header class="cu-header">
        <div>
            <p class="cu-eyebrow">Account overview</p>
            <h1>{{ $user->name }}</h1>
            <p class="cu-muted">
                {{ $user->email }} · Account #{{ $user->id }}
            </p>
        </div>

        <div class="cu-actions">
            <span class="cu-badge">
                {{ $user->is_admin ? 'Administrator' : 'Customer' }}
            </span>

            <a class="cu-button" href="{{ route('admin.users.index') }}">
                ← Back to customers
            </a>
        </div>
    </header>

    <div class="cu-notice">
        Read-only account view. Viewing this page does not change the
        customer’s profile, meal plans or weight history.
    </div>

    @if($errors->any())
        <div class="cu-notice cu-error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="cu-stats" aria-label="Customer summary">
        <article class="cu-stat">
            <span class="cu-stat-label">Profile weight</span>
            <strong class="cu-value">
                {{ \App\Services\Nutrition\WeightUnits::format($profile?->weight_kg) }} <small>lbs</small>
            </strong>
            <p class="cu-muted">Weight used in the nutrition profile</p>
        </article>

        <article class="cu-stat">
            <span class="cu-stat-label">Latest weigh-in</span>
            <strong class="cu-value">
                {{ \App\Services\Nutrition\WeightUnits::format($latestWeightEntry?->weight_kg) }}
                <small>lbs</small>
            </strong>
            <p class="cu-muted">
                {{ $latestWeightEntry
                    ? $latestWeightEntry->recorded_on->format('j M Y')
                    : 'No weigh-ins recorded' }}
            </p>
        </article>

        <article class="cu-stat">
            <span class="cu-stat-label">Saved meals</span>
            <strong class="cu-value">{{ number_format($savedMealCount) }}</strong>
            <p class="cu-muted">Reusable meal options</p>
        </article>

        <article class="cu-stat">
            <span class="cu-stat-label">Food preferences</span>
            <strong class="cu-value">
                {{ number_format($likedFoods->count() + $excludedFoods->count()) }}
            </strong>
            <p class="cu-muted">
                {{ $likedFoods->count() }} liked ·
                {{ $excludedFoods->count() }} excluded
            </p>
        </article>
    </section>

    <div class="cu-grid">
        <section class="cu-panel">
            <header class="cu-panel-head">
                <h2>Nutrition profile</h2>
                <span class="cu-badge">
                    {{ $ready ? 'Guide ready' : 'Needs attention' }}
                </span>
            </header>

            @if($profile)
                <dl class="cu-details">
                    <div>
                        <dt>Goal</dt>
                        <dd>{{ $goalLabels[$profile->goal] ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt>Activity</dt>
                        <dd>{{ $activityLabels[$profile->activity_level] ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt>Height</dt>
                        <dd>
                            {{ $profile->height_cm === null
                                ? 'Not set'
                                : $format($profile->height_cm).' cm' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Date of birth</dt>
                        <dd>{{ $profile->date_of_birth?->format('j M Y') ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt>Target weight</dt>
                        <dd>
                            {{ $profile->target_weight_kg === null
                                ? 'Not set'
                                : \App\Services\Nutrition\WeightUnits::format($profile->target_weight_kg).' lbs' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Daily pattern</dt>
                        <dd>
                            {{ $profile->meals_per_day ?? 'Not set' }} full meals
                            · {{ $profile->snacks_per_day ?? 0 }} optional snacks
                        </dd>
                    </div>
                    <div>
                        <dt>Time available per meal</dt>
                        <dd>
                            {{ $profile->cooking_minutes === null
                                ? 'Not set'
                                : $profile->cooking_minutes.' minutes' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Food style</dt>
                        <dd>{{ $dietLabels[$profile->dietary_preference] ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt>Resistance training</dt>
                        <dd>
                            {{ $profile->resistance_training === null
                                ? 'Not set'
                                : ($profile->resistance_training ? 'Yes' : 'No') }}
                        </dd>
                    </div>
                    <div>
                        <dt>Account created</dt>
                        <dd>
                            {{ $user->created_at?->timezone('Europe/London')->format('j M Y') ?? 'Unknown' }}
                        </dd>
                    </div>
                </dl>

                <h3>Allergies recorded</h3>
                <p class="cu-text">{{ filled($profile->allergies) ? $profile->allergies : 'None recorded' }}</p>

                <h3>Other exclusions or intolerances</h3>
                <p class="cu-text">{{ filled($profile->foods_to_avoid) ? $profile->foods_to_avoid : 'None recorded' }}</p>
            @else
                <div class="cu-empty">
                    This account has not saved a nutrition profile.
                </div>
            @endif
        </section>

        <section class="cu-panel">
            <header class="cu-panel-head">
                <h2>Current daily guides</h2>
            </header>

            @if($ready)
                <dl class="cu-details">
                    @foreach($macroCards as $macro)
                        <div>
                            <dt>{{ $macro['label'] }}</dt>
                            <dd>
                                {{ $format($targets[$macro['key']] ?? null, $macro['decimals']) }}
                                {{ $macro['unit'] }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="cu-note">
                    Calculated from this customer’s current profile and macro
                    settings. Existing meals retain their saved quantities.
                    Available carbohydrate differs from total carbohydrate.
                </p>
            @else
                <div class="cu-empty">
                    {{ $targets['message'] ?? 'A nutrition guide is not available.' }}
                </div>
            @endif

            <h3>Saved meal collection</h3>

            <dl class="cu-details">
                @foreach(['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snacks'] as $type => $label)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd>{{ number_format((int) $savedMealCounts->get($type, 0)) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <div class="cu-grid">
        @foreach($preferenceSections as $section)
            <section class="cu-panel {{ $section['class'] }}">
                <header class="cu-panel-head">
                    <h2>{{ $section['label'] }}</h2>
                    <span class="cu-badge">{{ $section['foods']->count() }} foods</span>
                </header>

                <ul class="cu-chips">
                    @forelse($section['foods'] as $food)
                        <li class="cu-chip">
                            {{ $food['name'] }}
                            <small>
                                {{ $food['category'] }}
                                {{ $food['is_available'] ? '' : '· Currently unavailable' }}
                            </small>
                        </li>
                    @empty
                        <li class="cu-muted">No explicit choices recorded.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>

    <section class="cu-panel cu-week-panel">
        <header class="cu-panel-head">
            <div>
                <h2>Customer meal plan</h2>
                <p class="cu-muted">
                    {{ $weekStart->format('j M') }} – {{ $weekEnd->format('j M Y') }}
                </p>
            </div>

            <form
                class="cu-week-form"
                method="GET"
                action="{{ route('admin.users.show', $user) }}"
            >
                <label for="customer-plan-date">Choose a date in the week</label>
                <input
                    id="customer-plan-date"
                    type="date"
                    name="date"
                    value="{{ $selectedDate->toDateString() }}"
                    required
                >
                <button class="cu-button" type="submit">View week</button>
            </form>
        </header>

        <p class="cu-muted">
            Confirmed planned meals. These are not records of food eaten.
        </p>

        <div class="cu-days">
            @foreach($days as $day)
                <section class="cu-day">
                    <header class="cu-day-head">
                        <h3>{{ $day['date']->format('l, j M') }}</h3>
                        <p>
                            {{ $day['entries']->count() }} meals ·
                            {{ $format($day['totals']['energy_kcal'] ?? null, 0) }} kcal ·
                            {{ $format($day['totals']['protein_g'] ?? null) }} g protein
                        </p>
                        <p>
                            {{ $format($day['totals']['estimated_available_carbohydrate_g'] ?? null) }} g available carbs ·
                            {{ $format($day['totals']['fat_g'] ?? null) }} g fat
                        </p>
                    </header>

                    @forelse($day['entries'] as $entry)
                        <div class="cu-meal">
                            <span class="cu-slot">
                                {{ $slots[$entry->slot] ?? ucfirst(str_replace('_', ' ', $entry->slot)) }}
                            </span>

                            @include('meals.details', [
                                'meal' => $entry->snapshot,
                            ])
                        </div>
                    @empty
                        <div class="cu-meal cu-muted">No meals planned.</div>
                    @endforelse
                </section>
            @endforeach
        </div>

        <p class="cu-note">
            A dash means nutrition is unavailable. Meal details show total
            carbohydrate; the daily summary shows available carbohydrate.
        </p>
    </section>

    <div class="cu-grid">
        <section class="cu-panel">
            <header class="cu-panel-head">
                <h2>Weight progress</h2>
                <span class="cu-badge">{{ $weightEntryCount }} entries</span>
            </header>

            @if($latestWeightEntry)
                <dl class="cu-details">
                    <div>
                        <dt>Starting weight</dt>
                        <dd>{{ \App\Services\Nutrition\WeightUnits::format($firstWeightEntry->weight_kg) }} lbs</dd>
                    </div>
                    <div>
                        <dt>Change since first entry</dt>
                        <dd>
                            {{ $weightChange > 0 ? '+' : '' }}{{ \App\Services\Nutrition\WeightUnits::format($weightChange) }} lbs
                        </dd>
                    </div>
                </dl>

                <h3>Latest ten weigh-ins</h3>

                <ul class="cu-compact-list">
                    @foreach($recentWeights as $weight)
                        <li>
                            <time datetime="{{ $weight->recorded_on->format('Y-m-d') }}">
                                {{ $weight->recorded_on->format('j M Y') }}
                            </time>
                            <strong>{{ \App\Services\Nutrition\WeightUnits::format($weight->weight_kg) }} lbs</strong>
                        </li>
                    @endforeach
                </ul>

                <p class="cu-note">
                    Weigh-ins are separate from the weight used for nutrition calculations.
                </p>
            @else
                <div class="cu-empty">No weigh-ins recorded.</div>
            @endif
        </section>

        <section class="cu-panel">
            <header class="cu-panel-head">
                <h2>Recent weekly requests</h2>
            </header>

            <ul class="cu-compact-list">
                @forelse($recentDrafts as $draft)
                    <li>
                        <div>
                            <strong>Week of {{ $draft->start_date->format('j M Y') }}</strong>
                            <p class="cu-muted">
                                Request #{{ $draft->id }} ·
                                {{ $draft->created_at->timezone('Europe/London')->format('j M Y, H:i') }}
                            </p>
                        </div>

                        <span class="cu-badge">
                            {{ $draftLabels[$draft->status] ?? ucfirst($draft->status) }}
                        </span>
                    </li>
                @empty
                    <li class="cu-muted">No weekly requests recorded.</li>
                @endforelse
            </ul>

            <p class="cu-note">
                Confirmation history does not guarantee meals remain in the
                current plan; customers can later swap or clear them.
            </p>
        </section>
    </div>
</div>
@endsection
