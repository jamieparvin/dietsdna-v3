@extends('layouts.app')

@section('title', 'Weekly meal preview')

@section('content')
@include('weekly-meals.styles')

<style>
    .wp-preview button:disabled { opacity:0.55; cursor:not-allowed; }

    .wp-preview {
        width: 100%;
        min-width: 0;
    }

    .wp-preview *,
    .wp-preview *::before,
    .wp-preview *::after {
        box-sizing: border-box;
    }

    .wp-heading {
        margin-bottom: 20px;
    }

    .wp-heading h1 {
        margin-bottom: 8px;
    }

    .wp-links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }

    .wp-preview .wm-button {
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 18px;
        text-align: center;
        text-decoration: none;
        white-space: normal;
    }

    .wp-days {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
        margin: 20px 0;
    }

    .wp-days[hidden],
    .wp-day[hidden] {
        display: none !important;
    }

    .wp-day-button {
        min-width: 0;
        min-height: 66px;
        padding: 10px 4px;
        border: 1px solid #d9e4dc;
        border-radius: 12px;
        background: #fff;
        color: #234b3e;
        cursor: pointer;
        font: inherit;
        text-align: center;
    }

    .wp-day-button span,
    .wp-day-button strong {
        display: block;
    }

    .wp-day-button span {
        font-size: 12px;
        margin-bottom: 5px;
    }

    .wp-day-button strong {
        font-size: 18px;
    }

    .wp-day-button[aria-pressed="true"] {
        background: #204d3c;
        border-color: #204d3c;
        color: #fff;
    }

    .wp-day-button:focus-visible,
    .wp-preview .wm-button:focus-visible,
    .wp-preview summary:focus-visible {
        outline: 3px solid #bc9148;
        outline-offset: 3px;
    }

    .wp-day {
        margin-bottom: 20px;
    }

    .wp-day-title {
        margin: 0 0 14px;
        font-size: 22px;
        color: #193d31;
    }

    .wp-totals {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .wp-total {
        min-width: 0;
        padding: 16px;
        background: #edf3ec;
        border: 1px solid #dce6dc;
        border-radius: 12px;
    }

    .wp-total span {
        display: block;
        margin-bottom: 7px;
        font-size: 13px;
        color: #526b5e;
    }

    .wp-total strong {
        display: block;
        font-size: 23px;
        color: #193d31;
    }

    .wp-total small {
        font-size: 13px;
        font-weight: 500;
    }

    .wp-meals {
        display: grid;
        gap: 14px;
    }

    .wp-meal {
        min-width: 0;
        padding: 20px;
        border: 1px solid #dce6df;
        border-radius: 14px;
        background: #fff;
    }

    .wp-meal-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 12px;
    }

    .wp-meal-slot {
        font-size: 14px;
        font-weight: 700;
        color: #345b47;
    }

    .wp-badge {
        padding: 5px 9px;
        border-radius: 20px;
        background: #eef4ee;
        color: #526b5e;
        font-size: 12px;
    }

    .wp-meal h3 {
        margin: 0 0 12px;
        color: #193d31;
        font-size: 18px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .wp-macros {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    .wp-macros span {
        padding: 7px 10px;
        border-radius: 8px;
        background: #f4f7f3;
        font-size: 13px;
        color: #3b594b;
    }

    .wp-meal summary {
        min-height: 44px;
        padding: 12px 0;
        cursor: pointer;
        color: #285b43;
        font-weight: 600;
        line-height: 1.5;
    }

    .wp-meal .wm-ingredients {
        padding-left: 22px;
        line-height: 1.8;
        overflow-wrap: anywhere;
    }

    .wp-meal .wm-method {
        white-space: pre-line;
        overflow-wrap: anywhere;
        line-height: 1.8;
    }

    .wp-confirm {
        margin-top: 22px;
        padding: 20px;
        background: #fff;
        border: 1px solid #dce6df;
        border-radius: 14px;
    }

    .wp-confirm p {
        margin: 0 0 16px;
    }

    .wp-confirm-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .wp-confirm-actions form {
        margin: 0;
    }

    @media (max-width: 600px) {
        .wp-preview {
            padding-left: 0;
            padding-right: 0;
        }

        .wp-heading h1 {
            font-size: 26px;
        }

        .wp-days {
            gap: 4px;
        }

        .wp-day-button {
            min-height: 62px;
            border-radius: 9px;
        }

        .wp-day-button span {
            font-size: 11px;
        }

        .wp-day-button strong {
            font-size: 17px;
        }

        .wp-totals {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .wp-total {
            padding: 14px;
        }

        .wp-total strong {
            font-size: 22px;
        }

        .wp-meal {
            padding: 16px;
        }

        .wp-links .wm-button {
            flex: 1 1 140px;
        }

        .wp-confirm {
            padding: 16px;
        }

        .wp-confirm-actions { gap: 8px; }
        .wp-preview .wp-confirm-actions .wm-button { padding: 12px 8px; font-size: 12px; }
    }

    .wp-total { padding:21px; background:#fff; border-color:#dfe7df; border-radius:15px; }
    .wp-total .wp-metric-label { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:650; color:#50665a; }
    .wp-total .wp-dot { width:8px; height:8px; border-radius:50%; background:var(--metric-color); flex:none; }
    .wp-total > strong { margin:15px 0 2px; font-size:29px; letter-spacing:-.6px; line-height:1.3; }
    .wp-total .wp-caption { margin:0; font-size:11px; color:#61756a; }
    .wp-total .wp-planned { margin:15px 0 8px; font-size:12px; color:#4f6558; line-height:1.5; }
    .wp-total .wp-planned b { color:#254937; }
    .wp-track { height:5px; background:#edf2ec; border-radius:8px; overflow:hidden; }
    .wp-track i { display:block; height:100%; background:var(--metric-color); border-radius:8px; }
    .wp-total .wp-difference { margin:9px 0 0; font-size:11px; color:#61756a; line-height:1.6; }
    .wp-nutrition-info { margin:-3px 0 20px; font-size:12px; color:#61756a; line-height:1.7; }
    .wp-nutrition-info summary { color:#365f46; cursor:pointer; font-weight:650; min-height:44px; padding:10px 0; }
    .wp-nutrition-info p { margin:0; }
    .wp-confirm h2 { margin:0 0 9px; font-size:19px; color:#193d31; }
    .wp-replace-check { display:flex; align-items:flex-start; gap:10px; line-height:1.6; margin:0 0 20px; cursor:pointer; font-size:13px; }
    .wp-replace-check input { width:20px!important; height:20px; flex:none; margin-top:2px; }
    .wp-confirm .wp-confirm-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; max-width:520px; }
    .wp-confirm .wp-confirm-actions form { min-width:0; width:100%; margin:0; display:flex; }
    .wp-preview .wp-confirm .wp-confirm-actions .wm-button { width:100%; min-height:48px; border-radius:9px; font-weight:700; }
    @media(max-width:600px) {
        .wp-total { padding:17px 14px; }
        .wp-total > strong { font-size:25px; }
        .wp-confirm .wp-confirm-actions { gap:8px; max-width:none; }
        .wp-preview .wp-confirm .wp-confirm-actions .wm-button { padding:12px 8px; font-size:12px; }
    }

</style>

<div class="wm-page wp-preview">
    <header class="wp-heading">
        <h1>{{ $replacing ? 'Your replacement preview' : 'Your weekly preview' }}</h1>

        <p class="wm-muted">
            {{ $draft->start_date->format('j M') }}
            –
            {{ $draft->start_date->addDays(6)->format('j M Y') }}
        </p>

        <div class="wp-links">
            <a
                class="wm-button wm-secondary"
                href="{{ route('meal-plan.index', [
                    'date' => $draft->start_date->toDateString(),
                ]) }}"
            >
                View meal plan
            </a>

            <a
                class="wm-button wm-secondary"
                href="{{ route('weekly-meals.create', [
                    'date' => $draft->start_date->toDateString(),
                ]) }}"
            >
                Weekly generation
            </a>
        </div>
    </header>

    @include('partials.food-allergy-notice')
    @include('partials.nutrition-estimate-disclaimer')

    @if(in_array($draft->status, ['queued', 'generating'], true))
        <section class="wm-panel">
            <h2 id="weekly-generation-status" aria-live="polite">
                {{ $draft->status === 'queued'
                    ? 'Waiting to start'
                    : 'Creating meal options' }}
            </h2>

            <p
                id="weekly-progress"
                class="wm-muted"
                role="status"
            >
                {{ $draft->completed_types }}
                of {{ $typeCount }} meal types completed.
            </p>

            <p class="wm-muted">
                We’re preparing your meals. You can leave this page
                and return later to check your progress.
            </p>

            @if($draft->completed_types >= $typeCount)
                <p class="wm-muted">Checking portions and refining meal combinations.</p>
            @endif
            <form
                method="POST"
                action="{{ route('weekly-meals.cancel', $draft->id) }}"
            >
                @csrf

                <button type="submit" class="wm-button wm-secondary">
                    Cancel preview
                </button>
            </form>

            <p class="wm-muted">
                Cancelling stops this preview.
                Your existing meal plan stays unchanged.
            </p>
        </section>

    @elseif($draft->status === 'failed')
        <div class="wm-notice wm-error" role="alert">
            {{ $draft->error }}
        </div>

        <p class="wm-muted">
            No further generation is running for this preview.
            Existing planned meals were not changed.
        </p>
        <a class="wm-button" href="{{ route('weekly-meals.create', ['date' => $draft->start_date->toDateString()]) }}">Try a new weekly preview</a>

    @elseif($draft->status === 'cancelled')
        <div class="wm-notice">
            Your current plan is kept. You can swap any meals you don’t like from your meal plan.
            <a href="{{ route('meal-plan.index', ['date' => $draft->start_date->toDateString()]) }}">Open meal plan to swap meals →</a>
        </div>

    @elseif($draft->status === 'confirmed')
        <div class="wm-notice">
            {{ $replacing ? 'This replacement has been applied.' : 'This week has been added.' }}
            Open your meal plan to view your meals and swap any you want to change.
        </div>

    @elseif($draft->status === 'ready')
        @if($previewBlocked)
            <div class="wm-notice" role="alert">
                <strong>This is an older preview and cannot be confirmed.</strong>
                {{ $previewBlocked }} Existing planned meals have not been changed.
            </div>
        @endif
        <div class="wm-notice">
            @if($replacing)
                Keep your current plan and swap individual meals you don’t like, or choose this new week instead.
                Review the days below, then make your choice at the bottom.
            @else
                Review your week, then add it to your meal plan. You can swap individual meals afterwards using your saved options.
                Meals marked “Keep existing” are already in your plan.
            @endif
        </div>
        @if($planChanged)
            <div class="wm-notice wm-error" role="alert">
                Your current plan changed after this preview was prepared. Generate a new preview before confirming.
                <a href="{{ route('weekly-meals.create', ['date' => $draft->start_date->toDateString(), 'mode' => $replacing ? 'replace' : 'fill']) }}">Create a fresh preview</a>
            </div>
        @endif


        <nav
            id="weekly-day-navigation"
            class="wp-days"
            aria-label="Choose a day to preview"
            hidden
        >
            @foreach($days as $day)
                <button
                    type="button"
                    class="wp-day-button"
                    data-day="{{ $day['date']->toDateString() }}"
                    aria-controls="preview-{{ $day['date']->toDateString() }}"
                    aria-pressed="false"
                    aria-label="{{ $day['date']->format('l, j F') }}"
                >
                    <span>{{ $day['date']->format('D') }}</span>
                    <strong>{{ $day['date']->format('j') }}</strong>
                </button>
            @endforeach
        </nav>

        <div id="weekly-day-panels">
            @foreach($days as $day)
                <section
                    id="preview-{{ $day['date']->toDateString() }}"
                    class="wp-day"
                    data-preview-day="{{ $day['date']->toDateString() }}"
                    aria-labelledby="heading-{{ $day['date']->toDateString() }}"
                >
                    <h2
                        id="heading-{{ $day['date']->toDateString() }}"
                        class="wp-day-title"
                    >
                        {{ $day['date']->format('l, j F') }}
                    </h2>

                    @php
                        $previewGuide = collect($day['rows'])
                            ->map(fn ($row) => $row['snapshot']['portion_balance'] ?? null)
                            ->filter()
                            ->sortByDesc(fn ($guide) => ($guide['version'] ?? null) === \App\Services\Meals\MealPortionBalancer::VERSION)
                            ->first();
                        $guideTargets = $previewGuide['daily_targets'] ?? [];
                        $previewNumber = fn ($value) => \App\Services\Nutrition\SnapshotNutrition::number($value);
                        $availableCarbs = $day['totals']['estimated_available_carbohydrate_g'];
                        $previewMetrics = [
                            ['label' => 'Calories', 'planned' => $previewNumber($day['totals']['energy_kcal'] ?? null), 'guide' => $previewNumber($guideTargets['calories_kcal'] ?? null), 'unit' => 'kcal', 'decimals' => 0, 'color' => '#19815a'],
                            ['label' => 'Protein', 'planned' => $previewNumber($day['totals']['protein_g'] ?? null), 'guide' => $previewNumber($guideTargets['protein_g'] ?? null), 'unit' => 'g', 'decimals' => 1, 'color' => '#6688ae'],
                            ['label' => 'Available carbs', 'planned' => $availableCarbs, 'guide' => $previewNumber($guideTargets['carbohydrate_g'] ?? null), 'unit' => 'g', 'decimals' => 1, 'color' => '#c49a51'],
                            ['label' => 'Fat', 'planned' => $previewNumber($day['totals']['fat_g'] ?? null), 'guide' => $previewNumber($guideTargets['fat_g'] ?? null), 'unit' => 'g', 'decimals' => 1, 'color' => '#a77997'],
                        ];
                    @endphp
                    <div class="wp-totals" aria-label="Daily nutrition guide and preview amounts">
                        @foreach($previewMetrics as $metric)
                            @php
                                $mainFigure = $metric['guide'] ?? $metric['planned'];
                                $percent = $metric['planned'] !== null && $metric['guide'] !== null && $metric['guide'] > 0 ? min(100, 100 * $metric['planned'] / $metric['guide']) : null;
                            @endphp
                            <div class="wp-total" style="--metric-color:{{ $metric['color'] }}">
                                <div class="wp-metric-label"><i class="wp-dot" aria-hidden="true"></i>{{ $metric['label'] }}</div>
                                <strong>{{ $mainFigure === null ? '—' : number_format($mainFigure, $metric['decimals']) }} <small>{{ $metric['unit'] }}</small></strong>
                                <p class="wp-caption">{{ $metric['guide'] === null ? 'Planned for this day' : 'Daily guide' }}</p>
                                <p class="wp-planned"><b>{{ $metric['planned'] === null ? '—' : number_format($metric['planned'], $metric['decimals']) }} {{ $metric['unit'] }}</b> in this preview</p>
                                @if($percent !== null)
                                <div class="wp-track" aria-hidden="true"><i style="width:{{ $percent }}%"></i></div>
                                @endif
                                <p class="wp-difference">
                                    @if($metric['planned'] !== null && $metric['guide'] !== null)
                                        {{ number_format(abs($metric['planned'] - $metric['guide']), $metric['decimals']) }} {{ $metric['unit'] }} {{ $metric['planned'] > $metric['guide'] ? 'above' : 'below' }} guide
                                    @elseif($metric['planned'] === null)
                                        Some planned nutrition is unavailable.
                                    @else
                                        Nutrition from the saved meal portions.
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                    <details class="wp-nutrition-info">
                        <summary>About these nutrition figures</summary>
                        <p>Figures describe the portions in this preview. Available carbs are estimated as total carbohydrate minus fibre; individual meal cards show total carbohydrate. Swapping a meal can change the day’s totals.</p>
                    </details>

                    <div class="wp-meals">
                        @foreach($day['rows'] as $row)
                            <article class="wp-meal">
                                <div class="wp-meal-heading">
                                    <span class="wp-meal-slot">
                                        {{ $row['label'] }}
                                    </span>

                                    <span class="wp-badge">
                                        {{ $row['existing']
                                            ? 'Keep existing'
                                            : 'New suggestion' }}
                                    </span>
                                </div>

                                @if($row['snapshot'])
                                    @if(!empty($row['excluded_foods']))
                                        <p class="wm-notice" role="status">
                                            <strong>Contains foods you now Dislike:</strong>
                                            {{ implode(', ', $row['excluded_foods']) }}.
                                            @if($row['existing'])
                                                This existing meal is preserved by Fill empty slots. Swap or remove it from your plan if you want to avoid these foods.
                                            @else
                                                This older suggestion cannot be added. Generate a fresh preview.
                                            @endif
                                        </p>
                                    @endif
                                    @php($meal = $row['snapshot'])

                                    <h3>{{ $meal['name'] }}</h3>
                                    @include('partials.meal-time')

                                    <div class="wp-macros">
                                        <span>
                                            {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['energy_kcal'] ?? null, 0) }}
                                            kcal
                                        </span>

                                        <span>
                                            {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['protein_g'] ?? null, 1) }}
                                            g protein
                                        </span>

                                        <span>
                                            {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['carbohydrate_g'] ?? null, 1) }}
                                            g total carbs
                                        </span>

                                        <span>
                                            {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['fat_g'] ?? null, 1) }}
                                            g fat
                                        </span>
                                    </div>

                                    <details>
                                        <summary>
                                            Ingredients and preparation
                                        </summary>

                                        <ul class="wm-ingredients">
                                            @foreach($meal['ingredients'] as $ingredient)
                                                <li>
                                                    {{ $ingredient['name'] }}
                                                    —
                                                    {{ number_format($ingredient['quantity_g'], 1) }}
                                                    g
                                                    ({{ $ingredient['preparation_state'] }})
                                                </li>
                                            @endforeach
                                        </ul>

                                        <div class="wm-method">{{ $meal['instructions'] }}</div>
                                    </details>
                                @else
                                    <p class="wm-muted">
                                        No suggestion for this slot.
                                        It may have been removed from your
                                        plan while the preview was open.
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <section class="wp-confirm" aria-labelledby="weekly-choice-heading">
            <h2 id="weekly-choice-heading">Make this week yours</h2>
            <p class="wm-muted">
                @if($replacing)
                    Choose <strong>Keep current plan</strong> to keep your existing week. Then open your meal plan and use <strong>Swap meal</strong> to replace any meals you don’t like with your saved options.
                    Or choose <strong>Replace this week</strong> to use the meals in this preview instead.
                @else
                    Add this week to your plan, then use <strong>Swap meal</strong> to replace any meals you don’t like with your saved options.
                @endif
            </p>

            @if($replacing)
                <label class="wp-replace-check">
                    <input type="checkbox" name="replace_confirmed" value="1" form="weekly-confirm-form" required>
                    <span>Replace my current plan for {{ $draft->start_date->format('j M') }} – {{ $draft->start_date->addDays(6)->format('j M Y') }} with these meals.</span>
                </label>
            @endif

            <div class="wp-confirm-actions">
                <form method="POST" action="{{ route('weekly-meals.cancel', $draft->id) }}">
                    @csrf
                    <button type="submit" class="wm-button wm-secondary">{{ $replacing ? 'Keep current plan' : 'Discard preview' }}</button>
                </form>
                <form method="POST" action="{{ route('weekly-meals.confirm', $draft->id) }}" id="weekly-confirm-form">
                    @csrf
                    <button type="submit" class="wm-button" id="weekly-confirm-button"
                        data-busy="{{ $replacing ? 'Replacing week…' : 'Adding meals…' }}"
                        @disabled($planChanged || $previewBlocked)>
                        {{ $replacing ? 'Replace this week' : 'Add this week to my plan' }}
                    </button>
                </form>
            </div>
        </section>
    @endif
</div>

@if(in_array($draft->status, ['queued', 'generating'], true))
    <script>
        (() => {
            const url = @json(route('weekly-meals.status', $draft->id));
            const progress = document.getElementById('weekly-progress');
            const heading = document.getElementById('weekly-generation-status');

            async function poll() {
                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });

                    if (!response.ok) {
                        throw new Error('Status unavailable');
                    }

                    const data = await response.json();

                    if (!['queued', 'generating'].includes(data.status)) {
                        window.location.reload();
                        return;
                    }

                    heading.textContent = data.status === 'queued' ? 'Waiting to start' : 'Creating meal options';
                    progress.textContent =
                        `${data.completed_types} of {{ $typeCount }} meal types completed.`;

                    setTimeout(poll, 4000);
                } catch (error) {
                    progress.textContent =
                        'Could not update progress. Refresh this page to check again.';
                }
            }

            setTimeout(poll, 4000);
        })();
    </script>
@endif

<script>
    (() => {
        const navigation = document.getElementById('weekly-day-navigation');

        if (navigation) {
            const buttons = [...navigation.querySelectorAll('[data-day]')];
            const panels = [
                ...document.querySelectorAll('[data-preview-day]'),
            ];

            function selectDay(date) {
                if (!buttons.some(button => button.dataset.day === date)) {
                    return;
                }

                buttons.forEach(button => {
                    button.setAttribute(
                        'aria-pressed',
                        button.dataset.day === date ? 'true' : 'false'
                    );
                });

                panels.forEach(panel => {
                    panel.hidden = panel.dataset.previewDay !== date;
                });
            }

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    selectDay(button.dataset.day);
                });
            });

            if (buttons.length) {
                selectDay(buttons[0].dataset.day);
                navigation.hidden = false;
            }
        }

        const form = document.getElementById('weekly-confirm-form');

        form?.addEventListener('submit', () => {
            const button = document.getElementById('weekly-confirm-button');

            button.disabled = true;
            button.textContent = button.dataset.busy || 'Adding meals…';
        });
    })();
</script>
@endsection
