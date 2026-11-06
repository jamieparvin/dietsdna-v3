@extends('layouts.app')

@section('title', 'Your progress')

@section('content')
@php
    $formatWeight = fn ($value) => $value === null
        ? '—'
        : \App\Services\Nutrition\WeightUnits::format($value);

    $chartWidth = 800;
    $chartHeight = 260;
    $left = 60;
    $right = 25;
    $top = 25;
    $bottom = 40;
    $plotWidth = $chartWidth - $left - $right;
    $plotHeight = $chartHeight - $top - $bottom;
    $dots = [];
    $linePoints = '';
    $ticks = [];

    if (count($chartPoints) > 0) {
        $weights = array_column($chartPoints, 'weight');
        $padding = max(0.5, (max($weights) - min($weights)) * 0.15);
        $minimum = max(0, min($weights) - $padding);
        $maximum = max($weights) + $padding;
        $range = $maximum - $minimum;

        $timestamps = array_map(
            fn ($point) => \Carbon\CarbonImmutable::parse(
                $point['date'],
                'UTC'
            )->timestamp,
            $chartPoints
        );

        $firstTimestamp = min($timestamps);
        $lastTimestamp = max($timestamps);
        $timeRange = $lastTimestamp - $firstTimestamp;

        foreach ($chartPoints as $index => $point) {
            $x = $timeRange > 0
                ? $left + (($timestamps[$index] - $firstTimestamp) / $timeRange) * $plotWidth
                : $left + $plotWidth / 2;

            $y = $top + (($maximum - $point['weight']) / $range) * $plotHeight;

            $dots[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'date' => $point['date'],
                'weight' => $point['weight'],
            ];
        }

        $linePoints = implode(' ', array_map(
            fn ($dot) => $dot['x'].','.$dot['y'],
            $dots
        ));

        for ($index = 0; $index <= 4; $index++) {
            $ticks[] = [
                'y' => $top + ($index / 4) * $plotHeight,
                'value' => $maximum - ($index / 4) * $range,
            ];
        }
    }
@endphp

<style>
    .pr-page {width:100%;color:#183d32;}
    .pr-header {display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;}
    .pr-eyebrow {margin:0 0 8px;color:#738575;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;}
    .pr-header h1 {margin:0 0 9px;font-size:31px;letter-spacing:-.7px;}
    .pr-muted {margin:0;color:#6b7d71;font-size:13px;line-height:1.7;}
    .pr-button {display:inline-flex;align-items:center;justify-content:center;min-height:44px;box-sizing:border-box;padding:10px 16px;border:1px solid #d5e0d8;border-radius:9px;background:#fff;color:#234d37;font:inherit;font-size:13px;font-weight:650;text-decoration:none;cursor:pointer;}
    .pr-primary {background:#1c4936;border-color:#1c4936;color:#fff;}
    .pr-danger {background:#fff5f2;border-color:#eaccc3;color:#874b3c;}
    .pr-button:focus-visible,.pr-page input:focus-visible,.pr-page summary:focus-visible {outline:3px solid #9fbea9;outline-offset:3px;}
    .pr-stats {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px;}
    .pr-stat,.pr-panel {min-width:0;padding:22px;border:1px solid #dfe7e1;border-radius:15px;background:#fff;}
    .pr-stat-label {display:flex;align-items:center;gap:8px;font-size:13px;font-weight:650;color:#50665a;}
    .pr-dot {width:8px;height:8px;flex-shrink:0;border-radius:50%;background:var(--pr-color,#19815a);}
    .pr-value {display:block;margin:14px 0 7px;font-size:29px;line-height:1.3;letter-spacing:-.5px;}
    .pr-value small {font-size:13px;font-weight:500;letter-spacing:0;}
    .pr-caption {font-size:11px;color:#6b7d71;line-height:1.6;}
    .pr-main {display:grid;grid-template-columns:minmax(0,1.8fr) minmax(280px,1fr);gap:20px;align-items:start;margin-bottom:22px;}
    .pr-panel h2 {margin:0 0 12px;font-size:20px;}
    .pr-panel-header {display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;}
    .pr-panel-header h2 {margin:0;}
    .pr-filters {display:flex;gap:6px;flex-wrap:wrap;}
    .pr-filters a {padding:9px 12px;min-height:44px;box-sizing:border-box;display:inline-flex;align-items:center;border:1px solid #dfe7e1;border-radius:8px;text-decoration:none;color:#50665a;font-size:12px;}
    .pr-filters a.is-active {background:#edf4ee;border-color:#9fbea9;color:#1c4936;font-weight:700;}
    .pr-chart {display:block;width:100%;height:auto;}
    .pr-chart text {font-family:inherit;font-size:12px;fill:#6b7d71;}
    .pr-empty {padding:40px 15px;text-align:center;border-radius:10px;background:#f7f9f4;}
    .pr-empty strong {display:block;margin-bottom:8px;font-size:17px;}
    .pr-field {margin-top:16px;}
    .pr-field label {display:block;margin-bottom:7px;font-size:13px;font-weight:650;}
    .pr-field input {width:100%;max-width:100%;box-sizing:border-box;min-height:44px;padding:11px 12px;border:1px solid #cbd9ce;border-radius:8px;background:#fff;color:#183d32;font:inherit;}
    .pr-form-actions {display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:20px;}
    .pr-notice {margin-bottom:20px;padding:15px 18px;border:1px solid #d9e5db;border-radius:11px;background:#edf4ee;font-size:13px;line-height:1.7;}
    .pr-error {border-color:#eaccc3;background:#fff5f2;color:#874b3c;}
    .pr-history {list-style:none;padding:0;margin:0;}
    .pr-history-row {display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:17px 0;border-top:1px solid #edf1eb;}
    .pr-history-date {display:block;font-size:14px;font-weight:650;margin-bottom:5px;}
    .pr-history-weight {font-size:16px;font-weight:750;}
    .pr-history-actions {display:flex;align-items:flex-start;gap:9px;flex-wrap:wrap;justify-content:flex-end;}
    .pr-delete {position:relative;}
    .pr-delete summary {display:inline-flex;align-items:center;min-height:44px;padding:0 10px;color:#874b3c;font-size:13px;cursor:pointer;}
    .pr-delete form {width:240px;max-width:100%;padding:14px;border:1px solid #eaccc3;border-radius:9px;background:#fffaf8;}
    .pr-confirm {display:flex;align-items:flex-start;gap:8px;font-size:12px;line-height:1.6;margin-bottom:12px;}
    .pr-confirm input {width:18px;height:18px;flex-shrink:0;margin-top:2px;accent-color:#1c4936;}
    .pr-pagination {margin-top:20px;}
    .pr-note {margin-top:15px;color:#758477;font-size:12px;line-height:1.8;}
    @media(max-width:1000px) {
        .pr-main {grid-template-columns:1fr;}
        .pr-stats {grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media(max-width:550px) {
        .pr-header h1 {font-size:26px;}
        .pr-stats {gap:10px;}
        .pr-stat {padding:16px 13px;}
        .pr-value {font-size:25px;}
        .pr-stat-label {font-size:12px;}
        .pr-panel {padding:18px 15px;}
        .pr-history-row {flex-wrap:wrap;}
        .pr-history-actions {width:100%;justify-content:flex-start;}
        .pr-chart {min-height:190px;}
    }
</style>

<div class="pr-page">
    <header class="pr-header">
        <div>
            <p class="pr-eyebrow">Your journey over time</p>
            <h1>Your progress</h1>
            <p class="pr-muted">
                Record your weight and see how it changes over time.
            </p>
        </div>

        <a class="pr-button" href="{{ route('nutrition-profile.edit') }}">
            View nutrition profile
        </a>
    </header>

    @if(session('status'))
        <div class="pr-notice" role="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="pr-notice pr-error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="pr-stats" aria-label="Weight summary">
        <article class="pr-stat">
            <div class="pr-stat-label">
                <span class="pr-dot" aria-hidden="true"></span>
                Starting weight
            </div>
            <strong class="pr-value">
                {{ $formatWeight($startingWeight) }} <small>lbs</small>
            </strong>
            <span class="pr-caption">
                {{ $firstEntry
                    ? 'First entry · '.$firstEntry->recorded_on->format('j M Y')
                    : 'Record your first weigh-in' }}
            </span>
        </article>

        <article class="pr-stat" style="--pr-color:#6688ae">
            <div class="pr-stat-label">
                <span class="pr-dot" aria-hidden="true"></span>
                Latest weight
            </div>
            <strong class="pr-value">
                {{ $formatWeight($latestWeight) }} <small>lbs</small>
            </strong>
            <span class="pr-caption">
                {{ $latestEntry
                    ? $latestEntry->recorded_on->format('j M Y')
                    : 'No entries yet' }}
            </span>
        </article>

        <article class="pr-stat" style="--pr-color:#c49a51">
            <div class="pr-stat-label">
                <span class="pr-dot" aria-hidden="true"></span>
                Weight change
            </div>
            <strong class="pr-value">
                {{ $weightChange !== null && $weightChange > 0 ? '+' : '' }}{{ $formatWeight($weightChange) }}
                <small>lbs</small>
            </strong>
            <span class="pr-caption">Since your first recorded entry</span>
        </article>

        <article class="pr-stat" style="--pr-color:#a77997">
            <div class="pr-stat-label">
                <span class="pr-dot" aria-hidden="true"></span>
                Target weight
            </div>
            <strong class="pr-value">
                {{ $formatWeight($targetWeight) }} <small>lbs</small>
            </strong>
            <span class="pr-caption">
                @if($remainingWeight !== null)
                    {{ $formatWeight($remainingWeight) }} lbs difference from target
                @elseif($targetWeight !== null)
                    From your nutrition profile
                @else
                    Set a target in your nutrition profile
                @endif
            </span>
        </article>
    </section>

    @include('partials.bmi-summary')

    <div class="pr-main">
        <section class="pr-panel">
            <header class="pr-panel-header">
                <h2>Weight history</h2>

                <nav class="pr-filters" aria-label="Chart date range">
                    @foreach([30, 90, 365] as $rangeDays)
                        <a
                            href="{{ route('progress.index', ['days' => $rangeDays]) }}"
                            class="{{ $days === $rangeDays ? 'is-active' : '' }}"
                            @if($days === $rangeDays)
                                aria-current="true"
                            @endif
                        >
                            {{ $rangeDays === 365 ? '1 year' : $rangeDays.' days' }}
                        </a>
                    @endforeach
                </nav>
            </header>

            @if(count($dots) > 0)
                <svg
                    class="pr-chart"
                    viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
                    role="img"
                    aria-labelledby="weight-chart-title weight-chart-description"
                >
                    <title id="weight-chart-title">
                        Recorded weight (lbs) over the last {{ $days }} days
                    </title>

                    <desc id="weight-chart-description">
                        Weight in lbs. {{ count($dots) }} recorded measurements.
                        @foreach($dots as $dot)
                            {{ $dot['date'] }}: {{ number_format($dot['weight'], 1) }} pounds (lbs).
                        @endforeach
                    </desc>

                    <text x="{{ $left }}" y="16">Weight (lbs)</text>
                    @foreach($ticks as $tick)
                        <line
                            x1="{{ $left }}"
                            y1="{{ $tick['y'] }}"
                            x2="{{ $chartWidth - $right }}"
                            y2="{{ $tick['y'] }}"
                            stroke="#e8eee8"
                        />
                        <text
                            x="{{ $left - 10 }}"
                            y="{{ $tick['y'] + 4 }}"
                            text-anchor="end"
                        >{{ number_format($tick['value'], 1) }}</text>
                    @endforeach

                    @if(count($dots) > 1)
                        <polyline
                            points="{{ $linePoints }}"
                            fill="none"
                            stroke="#19815a"
                            stroke-width="3"
                            stroke-linejoin="round"
                            stroke-linecap="round"
                        />
                    @endif

                    @foreach($dots as $dot)
                        <circle
                            cx="{{ $dot['x'] }}"
                            cy="{{ $dot['y'] }}"
                            r="4"
                            fill="#19815a"
                            stroke="#fff"
                            stroke-width="2"
                        >
                            <title>
                                {{ $dot['date'] }} · {{ number_format($dot['weight'], 1) }} lbs
                            </title>
                        </circle>
                    @endforeach

                    <text
                        x="{{ $left }}"
                        y="{{ $chartHeight - 10 }}"
                    >
                        {{ \Carbon\CarbonImmutable::parse($chartPoints[0]['date'])->format('j M Y') }}
                    </text>

                    <text x="{{ $chartWidth / 2 }}" y="{{ $chartHeight - 10 }}" text-anchor="middle">Date</text>
                    @if(count($chartPoints) > 1)
                        <text
                            x="{{ $chartWidth - $right }}"
                            y="{{ $chartHeight - 10 }}"
                            text-anchor="end"
                        >
                            {{ \Carbon\CarbonImmutable::parse($chartPoints[count($chartPoints) - 1]['date'])->format('j M Y') }}
                        </text>
                    @endif
                </svg>

                <p class="pr-note">
                    Weight in pounds (lbs). Points show recorded measurements;
                    the connecting line does not represent measurements between entries.
                    Summary cards use your recorded history up to today.
                </p>
            @else
                <div class="pr-empty">
                    <strong>No measurements in this period</strong>
                    <p class="pr-muted">
                        Add a weigh-in or choose a different date range.
                    </p>
                </div>
            @endif
        </section>

        <section class="pr-panel" id="weight-entry-form">
            <h2>{{ $editingEntry ? 'Edit weigh-in' : 'Record your weight' }}</h2>

            <p class="pr-muted">
                One entry per day. You can record previous dates.
            </p>

            <form
                method="POST"
                action="{{ $editingEntry
                    ? route('progress.update', ['entry' => $editingEntry->id])
                    : route('progress.store') }}"
            >
                @csrf

                @if($editingEntry)
                    @method('PUT')
                @endif

                <div class="pr-field">
                    <label for="recorded_on">Date</label>
                    <input
                        id="recorded_on"
                        type="date"
                        name="recorded_on"
                        value="{{ old('recorded_on', $editingEntry?->recorded_on->format('Y-m-d') ?? $today->toDateString()) }}"
                        min="1900-01-01"
                        max="{{ $today->toDateString() }}"
                        required
                    >
                </div>

                <div class="pr-field">
                    <label for="weight_lbs">Weight (lbs)</label>
                    <input
                        id="weight_lbs"
                        type="number"
                        name="weight_lbs"
                        value="{{ \App\Services\Nutrition\WeightUnits::oldInput('weight_lbs', 'weight_kg', $editingEntry?->weight_kg) }}"
                        min="{{ \App\Services\Nutrition\WeightUnits::inputLimit(20) }}"
                        max="{{ \App\Services\Nutrition\WeightUnits::inputLimit(500) }}"
                        step="any"
                        inputmode="decimal"
                        placeholder="e.g. 180.0"
                        required
                    >
                </div>

                <div class="pr-form-actions">
                    <button class="pr-button pr-primary" type="submit">
                        {{ $editingEntry ? 'Save changes' : 'Save weigh-in' }}
                    </button>

                    @if($editingEntry)
                        <a
                            class="pr-button"
                            href="{{ route('progress.index', ['days' => $days]) }}"
                        >
                            Cancel
                        </a>
                    @endif
                </div>
            </form>

            <p class="pr-note">
                Recording weight here does not change your nutrition-profile
                weight, calorie guides or existing meal plans.
            </p>
        </section>
    </div>

    <section class="pr-panel">
        <header class="pr-panel-header">
            <h2>Your weigh-ins</h2>
            <span class="pr-muted">{{ $entries->total() }} recorded entries</span>
        </header>

        <ul class="pr-history">
            @forelse($entries as $entry)
                <li class="pr-history-row">
                    <div>
                        <time
                            class="pr-history-date"
                            datetime="{{ $entry->recorded_on->format('Y-m-d') }}"
                        >
                            {{ $entry->recorded_on->format('l, j F Y') }}
                        </time>

                        <span class="pr-history-weight">
                            {{ \App\Services\Nutrition\WeightUnits::format($entry->weight_kg) }} lbs
                        </span>
                    </div>

                    <div class="pr-history-actions">
                        <a
                            class="pr-button"
                            href="{{ route('progress.index', [
                                'days' => $days,
                                'edit' => $entry->id,
                            ]) }}#weight-entry-form"
                        >
                            Edit
                        </a>

                        <details class="pr-delete">
                            <summary>Delete</summary>

                            <form
                                method="POST"
                                action="{{ route('progress.destroy', ['entry' => $entry->id]) }}"
                            >
                                @csrf
                                @method('DELETE')

                                <label class="pr-confirm">
                                    <input
                                        type="checkbox"
                                        name="confirm_delete"
                                        value="1"
                                        required
                                    >
                                    <span>
                                        Delete the weigh-in for
                                        {{ $entry->recorded_on->format('j M Y') }}?
                                    </span>
                                </label>

                                <button class="pr-button pr-danger" type="submit">
                                    Delete entry
                                </button>
                            </form>
                        </details>
                    </div>
                </li>
            @empty
                <li class="pr-empty">
                    <strong>Your first entry starts your history</strong>
                    <p class="pr-muted">
                        Use the form above to record a dated weight.
                    </p>
                </li>
            @endforelse
        </ul>

        @if($entries->hasPages())
            <div class="pr-pagination">
                {{ $entries->links() }}
            </div>
        @endif
    </section>
</div>
@endsection