@extends('layouts.app')

@section('title', 'Weekly meal preview')

@section('content')
@include('weekly-meals.styles')

<style>
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

        .wp-confirm-actions {
            flex-direction: column;
        }

        .wp-confirm-actions form,
        .wp-confirm-actions button {
            width: 100%;
        }
    }
</style>

<div class="wm-page wp-preview">
    <header class="wp-heading">
        <h1>Your weekly preview</h1>

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

    @if($errors->any())
        <div class="wm-notice wm-error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(in_array($draft->status, ['queued', 'generating'], true))
        <section class="wm-panel">
            <h2>
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
            The request was not automatically retried.
            Existing planned meals were not changed.
        </p>

    @elseif($draft->status === 'cancelled')
        <div class="wm-notice">
            This preview was cancelled.
            Your meal plan was not changed.
        </div>

    @elseif($draft->status === 'confirmed')
        <div class="wm-notice">
            This week has been added. Open your meal plan
            to view your meals and swap any you want to change.
        </div>

    @elseif($draft->status === 'ready')
        <div class="wm-notice">
            Review each day before adding the week.
            Meals marked “Keep existing” stay in your plan.
            New suggestions fill empty slots and are also saved
            as reusable meal options.
        </div>

        <p class="wm-muted">
            Totals reflect the portions shown. They may differ
            from your daily calorie and macro guides.
        </p>

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

                    <div class="wp-totals">
                        <div class="wp-total">
                            <span>Calories</span>
                            <strong>
                                {{ number_format($day['totals']['energy_kcal']) }}
                                <small>kcal</small>
                            </strong>
                        </div>

                        <div class="wp-total">
                            <span>Protein</span>
                            <strong>
                                {{ number_format($day['totals']['protein_g'], 1) }}
                                <small>g</small>
                            </strong>
                        </div>

                        <div class="wp-total">
                            <span>Total carbs</span>
                            <strong>
                                {{ number_format($day['totals']['carbohydrate_g'], 1) }}
                                <small>g</small>
                            </strong>
                        </div>

                        <div class="wp-total">
                            <span>Fat</span>
                            <strong>
                                {{ number_format($day['totals']['fat_g'], 1) }}
                                <small>g</small>
                            </strong>
                        </div>
                    </div>

                    @include('meals.portion-guide', ['day' => $day])
                    
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
                                    @php($meal = $row['snapshot'])

                                    <h3>{{ $meal['name'] }}</h3>

                                    <div class="wp-macros">
                                        <span>
                                            {{ number_format($meal['totals']['energy_kcal']) }}
                                            kcal
                                        </span>

                                        <span>
                                            {{ number_format($meal['totals']['protein_g'], 1) }}
                                            g protein
                                        </span>

                                        <span>
                                            {{ number_format($meal['totals']['carbohydrate_g'], 1) }}
                                            g total carbs
                                        </span>

                                        <span>
                                            {{ number_format($meal['totals']['fat_g'], 1) }}
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

        <section class="wp-confirm">
            <p class="wm-muted">
                Add the full week when you’re ready.
                Your existing meals will stay in place.
                You can swap meals afterwards from your meal plan.
            </p>

            <div class="wp-confirm-actions">
                <form
                    method="POST"
                    action="{{ route('weekly-meals.confirm', $draft->id) }}"
                    id="weekly-confirm-form"
                >
                    @csrf

                    <button
                        type="submit"
                        class="wm-button"
                        id="weekly-confirm-button"
                    >
                        Add this week to my plan
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('weekly-meals.cancel', $draft->id) }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="wm-button wm-secondary"
                    >
                        Discard preview
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
            button.textContent = 'Adding meals…';
        });
    })();
</script>
@endsection