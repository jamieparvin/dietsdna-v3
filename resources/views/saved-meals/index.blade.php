@extends('layouts.app')

@section('title', 'Saved meals')

@section('content')
@include('meals.styles')

<style>
    .sm-header {
        display:flex;
        justify-content:space-between;
        align-items:start;
        gap:20px;
        flex-wrap:wrap;
        margin-bottom:24px;
    }

    .sm-header .mp-toolbar {
        margin:0;
    }

    .sm-navigation {
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-bottom:30px;
    }

    .sm-navigation a {
        padding:10px 16px;
        border:1px solid #d7e2d9;
        border-radius:24px;
        background:#fff;
        color:#244c37;
        text-decoration:none;
        font-weight:600;
        font-size:14px;
    }

    .sm-section {
        margin-bottom:36px;
        scroll-margin-top:25px;
    }

    .sm-section-heading {
        display:flex;
        align-items:center;
        gap:12px;
        margin-bottom:17px;
    }

    .sm-section-heading h2 {
        margin:0;
        font-size:23px;
    }

    .sm-count {
        padding:5px 10px;
        border-radius:20px;
        background:#e7eee8;
        color:#64786b;
        font-size:13px;
    }

    .sm-grid {
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:18px;
        align-items:start;
    }

    .sm-card {
        margin-bottom:0;
    }

    .sm-card h3 {
        font-size:18px;
        line-height:1.5;
    }

    .sm-card-actions {
        margin-top:22px;
        padding-top:18px;
        border-top:1px solid #e9eee9;
    }

    .sm-empty {
        padding:22px;
        border:1px dashed #cddbd0;
        border-radius:13px;
        color:#6b7d71;
        line-height:1.7;
    }

    .sm-pagination {
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:18px;
        margin-top:18px;
        font-size:14px;
    }

    .sm-dialog {
        box-sizing:border-box;
        width:calc(100% - 32px);
        max-width:500px;
        max-height:90vh;
        padding:0;
        overflow:auto;
        border:1px solid #d6e1d8;
        border-radius:18px;
        color:#183d32;
        background:#fff;
        box-shadow:0 24px 80px rgba(15,40,26,.2);
    }

    .sm-dialog::backdrop {
        background:rgba(15,35,25,.48);
    }

    .sm-dialog-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:16px;
        padding:23px 24px 0;
    }

    .sm-dialog-header h2 {
        margin:0;
        font-size:23px;
    }

    .sm-close {
        display:flex;
        align-items:center;
        justify-content:center;
        width:36px;
        height:36px;
        border:0;
        border-radius:50%;
        background:#edf3ed;
        color:#284e38;
        font-size:24px;
        cursor:pointer;
    }

    .sm-dialog-body {
        padding:0 24px 24px;
    }

    .sm-selected-meal {
        padding:14px 16px;
        margin:20px 0;
        border-radius:10px;
        background:#edf4ee;
        font-weight:600;
        line-height:1.6;
    }

    .sm-field {
        margin-bottom:18px;
    }

    .sm-field label {
        display:block;
        margin-bottom:8px;
        font-size:14px;
        font-weight:700;
    }

    .sm-dialog input,
    .sm-dialog select {
        box-sizing:border-box;
        width:100%;
        min-height:46px;
        padding:11px 12px;
        border:1px solid #cedbd1;
        border-radius:9px;
        background:#fff;
        color:#183d32;
        font:inherit;
    }

    .sm-dialog-actions {
        display:flex;
        justify-content:flex-end;
        flex-wrap:wrap;
        gap:10px;
        margin-top:24px;
    }

    .sm-cancel {
        padding:11px 17px;
        border:1px solid #d5dfd7;
        border-radius:8px;
        background:#fff;
        color:#31543e;
        font:inherit;
        cursor:pointer;
    }

    .sm-dialog button:focus-visible,
    .sm-dialog input:focus-visible,
    .sm-dialog select:focus-visible {
        outline:3px solid #9fbea9;
        outline-offset:3px;
    }

    @media(max-width:1400px) {
        .sm-grid {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }
    }

    @media(max-width:900px) {
        .sm-grid {
            grid-template-columns:1fr;
        }
    }
</style>

<div class="mp-page">
    <header class="sm-header">
        <div>
            <h1>Your saved meals</h1>

            <p class="mp-muted">
                Your collection of meal options, ready whenever you need them.
                Choose a meal to add it to your plan.
            </p>
        </div>

        <div class="mp-toolbar">
            <a class="mp-button" href="{{ route('meal-ideas.index') }}">
                Find meal ideas
            </a>

            <a href="{{ route('meal-plan.index', ['date' => $date]) }}">
                View meal plan
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

    @if(!$slots)
        <div class="sm-empty" style="margin-bottom:24px">
            Your saved meals are available below.
            Choose your meals per day in
            <a href="{{ route('nutrition-profile.edit') }}">Nutrition profile</a>
            before adding meals to a plan.
        </div>
    @endif

    <nav class="sm-navigation" aria-label="Saved meal sections">
        @foreach($mealGroups as $type => $group)
            <a href="#{{ $type }}">
                {{ $group['label'] }}
                · {{ $group['meals']->total() }}
            </a>
        @endforeach
    </nav>

    @foreach($mealGroups as $type => $group)
        @php($meals = $group['meals'])

        <section class="sm-section" id="{{ $type }}">
            <div class="sm-section-heading">
                <h2>{{ $group['label'] }}</h2>

                <span class="sm-count">
                    {{ $meals->total() }}
                    {{ $meals->total() === 1 ? 'meal' : 'meals' }}
                </span>
            </div>

            @if($meals->isEmpty())
                <div class="sm-empty">
                    No {{ $type === 'snack' ? 'snack' : $type }} options
                    saved on this page yet.

                    <a href="{{ route('meal-ideas.index', ['meal_type' => $type]) }}">
                        Explore meal ideas
                    </a>
                </div>
            @else
                <div class="sm-grid">
                    @foreach($meals as $savedMeal)
                        <article class="mp-card sm-card">
                            @include('meals.details', [
                                'meal' => $savedMeal->snapshot,
                            ])

                            @if($slots)
                                <div class="sm-card-actions">
                                    <button
                                        type="button"
                                        class="mp-button"
                                        data-open-plan
                                        data-meal-id="{{ $savedMeal->id }}"
                                        data-meal-name="{{ $savedMeal->snapshot['name'] }}"
                                        data-meal-type="{{ $savedMeal->meal_type }}"
                                    >
                                        Add to meal plan
                                    </button>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            @if($meals->hasPages())
                <div class="sm-pagination">
                    @if($meals->previousPageUrl())
                        <a href="{{ $meals->previousPageUrl() }}">Previous</a>
                    @endif

                    <span class="mp-muted">
                        Page {{ $meals->currentPage() }}
                        of {{ $meals->lastPage() }}
                    </span>

                    @if($meals->nextPageUrl())
                        <a href="{{ $meals->nextPageUrl() }}">Next</a>
                    @endif
                </div>
            @endif
        </section>
    @endforeach
</div>

@if($slots)
    <dialog
        id="add-meal-dialog"
        class="sm-dialog"
        aria-labelledby="add-meal-title"
        aria-describedby="selected-meal-name"
    >
        <div class="sm-dialog-header">
            <h2 id="add-meal-title">Add to meal plan</h2>

            <button
                type="button"
                class="sm-close"
                data-close-plan
                aria-label="Close"
            >
                ×
            </button>
        </div>

        <div class="sm-dialog-body">
            <p id="selected-meal-name" class="sm-selected-meal"></p>

            <form method="POST" action="{{ route('meal-plan.store') }}">
                @csrf

                <input
                    id="selected-saved-meal"
                    type="hidden"
                    name="saved_meal_id"
                >

                <div class="sm-field">
                    <label for="selected-plan-date">Choose a date</label>

                    <input
                        id="selected-plan-date"
                        type="date"
                        name="plan_date"
                        value="{{ $date }}"
                        required
                        autofocus
                    >
                </div>

                <div class="sm-field">
                    <label for="selected-plan-slot">Choose a meal slot</label>

                    <select
                        id="selected-plan-slot"
                        name="slot"
                        required
                    >
                        @foreach($slots as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <p class="mp-muted">
                    This adds the saved portion unchanged.
                    If the slot is occupied, your existing meal will be kept.
                </p>

                <div class="sm-dialog-actions">
                    <button
                        type="button"
                        class="sm-cancel"
                        data-close-plan
                    >
                        Cancel
                    </button>

                    <button class="mp-button" type="submit">
                        Add meal
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
    (() => {
        const dialog = document.getElementById('add-meal-dialog');
        const mealId = document.getElementById('selected-saved-meal');
        const mealName = document.getElementById('selected-meal-name');
        const dateInput = document.getElementById('selected-plan-date');
        const slotInput = document.getElementById('selected-plan-slot');
        const buttons = [...document.querySelectorAll('[data-open-plan]')];

        const defaultDate = @json($date);

        const previous = {
            hasErrors: @json($errors->any()),
            mealId: @json(old('saved_meal_id')),
            date: @json(old('plan_date')),
            slot: @json(old('slot'))
        };

        function openDialog(button, restorePrevious = false) {
            mealId.value = button.dataset.mealId;
            mealName.textContent = button.dataset.mealName;

            dateInput.value = restorePrevious && previous.date
                ? previous.date
                : defaultDate;

            const preferredSlot = button.dataset.mealType === 'snack'
                ? 'snack_1'
                : button.dataset.mealType;

            const availableSlots = [...slotInput.options]
                .map(option => option.value);

            const requestedSlot = restorePrevious && previous.slot
                ? previous.slot
                : preferredSlot;

            slotInput.value = availableSlots.includes(requestedSlot)
                ? requestedSlot
                : availableSlots[0];

            dialog.showModal();
        }

        buttons.forEach(button => {
            button.addEventListener('click', () => openDialog(button));
        });

        dialog.querySelectorAll('[data-close-plan]').forEach(button => {
            button.addEventListener('click', () => dialog.close());
        });

        if (previous.hasErrors && previous.mealId) {
            const previousButton = buttons.find(button =>
                button.dataset.mealId === String(previous.mealId)
            );

            if (previousButton) {
                openDialog(previousButton, true);
            }
        }
    })();
    </script>
@endif
@endsection