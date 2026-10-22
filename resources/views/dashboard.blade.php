@extends('layouts.app')

@section('title', 'Your dashboard')

@section('content')
    <style>
        .dna-dashboard {
            --dna-green: #167451;
            --dna-dark: #143d30;
        }

        .dna-dashboard h2,
        .dna-dashboard h3 {
            margin: 0;
        }

        .dna-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
        }

        .dna-welcome {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 28px;
            padding: 38px;
            border-radius: 22px;
            color: white;
            background: linear-gradient(120deg, #153f31, #197653);
        }

        .dna-welcome::after {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            right: -100px;
            top: -120px;
            border: 48px solid rgba(255, 255, 255, .05);
            border-radius: 50%;
            pointer-events: none;
        }

        .dna-welcome .dna-eyebrow { color: #b8dbc9; }

        .dna-welcome h1 {
            margin-top: 16px;
            max-width: 650px;
            font-size: clamp(28px, 3.5vw, 42px);
            line-height: 1.15;
            letter-spacing: -1.2px;
        }

        .dna-welcome p {
            max-width: 530px;
            color: #d5e9de;
            margin-bottom: 0;
        }

        .dna-date {
            position: relative;
            z-index: 1;
            min-width: 130px;
            text-align: center;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 16px;
            background: rgba(255, 255, 255, .07);
        }

        .dna-date strong {
            display: block;
            margin: 6px 0;
            font-size: 44px;
            font-weight: 500;
        }

        .dna-date span {
            font-size: 13px;
            color: #d5e9de;
        }

        .dna-setup {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 18px;
            padding: 23px 26px;
            margin-top: 22px;
            border: 1px solid #e5dec9;
            border-radius: 16px;
            background: #fcf8ee;
        }

        .dna-setup h2 { font-size: 17px; }

        .dna-setup p {
            margin: 6px 0 0;
            font-size: 14px;
            color: #756951;
        }

        .dna-setup a {
            flex-shrink: 0;
            color: #665124;
            font-weight: 700;
            font-size: 14px;
            text-underline-offset: 4px;
        }

        .dna-section-heading {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 10px;
            margin: 32px 0 18px;
        }

        .dna-section-heading h2 {
            font-size: 21px;
            letter-spacing: -.4px;
        }

        .dna-section-heading span {
            color: #61756a;
            font-size: 13px;
        }

        .dna-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .dna-metric {
            background: white;
            border: 1px solid #dde7e1;
            border-radius: 16px;
            padding: 22px;
        }

        .dna-metric-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #53675e;
            font-size: 13px;
        }

        .dna-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--metric-color);
        }

        .dna-metric-value {
            display: block;
            margin-top: 16px;
            font-size: 30px;
            color: #17332c;
        }

        .dna-metric-value small {
            font-size: 13px;
            color: #61756a;
            font-weight: 400;
        }

        .dna-track {
            height: 5px;
            margin-top: 18px;
            border-radius: 5px;
            background: #edf2ef;
        }

        .dna-metric p {
            margin: 12px 0 0;
            font-size: 12px;
        }

        .dna-columns {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
            gap: 22px;
            margin-top: 24px;
        }

        .dna-panel {
            padding: 26px;
            background: white;
            border: 1px solid #dde7e1;
            border-radius: 18px;
        }

        .dna-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .dna-panel-header h2 { font-size: 19px; }

        .dna-status {
            padding: 6px 9px;
            border-radius: 6px;
            background: #f0f4f1;
            color: #61756a;
            font-size: 11px;
        }

        .dna-meal {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 0;
            border-top: 1px solid #edf2ef;
        }

        .dna-meal-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: #39745a;
            background: #edf5ef;
            font-size: 12px;
            font-weight: 700;
        }

        .dna-meal h3 { font-size: 14px; }

        .dna-meal p {
            margin: 5px 0 0;
            font-size: 13px;
        }

        .dna-empty {
            padding: 22px;
            border-radius: 14px;
            background: #f6f8f5;
        }

        .dna-empty h3 { font-size: 16px; }

        .dna-empty p {
            margin-bottom: 0;
            font-size: 14px;
        }

        .dna-progress {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #edf2ef;
        }

        .dna-progress-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 15px;
            font-size: 13px;
        }

        .dna-progress-row span { color: #61756a; }

        .dna-explore {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .dna-explore details {
            align-self: start;
            padding: 22px;
            border: 1px solid #dde7e1;
            border-radius: 16px;
            background: white;
        }

        .dna-explore summary {
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            line-height: 1.5;
        }

        .dna-explore p {
            margin-bottom: 0;
            font-size: 13px;
        }

        .dna-footer-note {
            margin-top: 26px;
            text-align: center;
            font-size: 12px;
            color: #61756a;
        }

        @media (max-width: 1100px) {
            .dna-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dna-columns { grid-template-columns: 1fr; }

            .dna-explore { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .dna-welcome {
                grid-template-columns: 1fr;
                padding: 26px;
            }

            .dna-date { display: none; }

            .dna-metrics { gap: 10px; }

            .dna-metric { padding: 17px; }

            .dna-panel { padding: 20px; }
        }
    </style>

    @php
        $today = now()->timezone('Europe/London');

        $nutritionCards = [
            ['label' => 'Calories', 'unit' => 'kcal', 'color' => '#19815a'],
            ['label' => 'Protein', 'unit' => 'g', 'color' => '#6b8bb5'],
            ['label' => 'Carbohydrates', 'unit' => 'g', 'color' => '#c89b4e'],
            ['label' => 'Fat', 'unit' => 'g', 'color' => '#ac80aa'],
        ];
    @endphp

    <div class="dna-dashboard">
        <section class="dna-welcome" aria-labelledby="welcome-heading">
            <div>
                <div class="dna-eyebrow">Your daily space</div>

                <h1 id="welcome-heading">
                    A fresh start,<br>
                    {{ auth()->user()->name }}.
                </h1>

                <p>
                    Make room for better habits.
                    Your meals, nutrition and progress, together in one place.
                </p>
            </div>

            <div class="dna-date" aria-label="{{ $today->format('l j F Y') }}">
                <span>{{ $today->format('l') }}</span>
                <strong>{{ $today->format('d') }}</strong>
                <span>{{ $today->format('F Y') }}</span>
            </div>
        </section>

        <section class="dna-setup" aria-labelledby="setup-heading">
            <div>
                <h2 id="setup-heading">Make DietsDNA your own</h2>
                <p>
                    Your goals, preferences and allergies will help shape your plan.
                </p>
            </div>

            <a href="{{ route('nutrition-profile.edit') }}">
                Edit your nutrition profile →
            </a>
        </section>

        <div class="dna-section-heading">
            <h2>Today at a glance</h2>
            <span>{{ $today->format('j F Y') }} · No food logged yet</span>
        </div>

        <section class="dna-metrics" aria-label="Daily nutrition summary">
            @foreach ($nutritionCards as $metric)
                <article class="dna-metric">
                    <div class="dna-metric-label">
                        <span
                            class="dna-dot"
                            style="--metric-color: {{ $metric['color'] }};"
                            aria-hidden="true"
                        ></span>

                        {{ $metric['label'] }}
                    </div>

                    <strong class="dna-metric-value">
                        <span aria-label="Not available">—</span>
                        <small>{{ $metric['unit'] }}</small>
                    </strong>

                    <div class="dna-track" aria-hidden="true"></div>

                    <p>Available when food logging is connected.</p>
                </article>
            @endforeach
        </section>

        <div class="dna-columns">
            <section class="dna-panel" aria-labelledby="meals-heading">
                <div class="dna-panel-header">
                    <h2 id="meals-heading">Your daily menu</h2>
                    <span class="dna-status">No plan yet</span>
                </div>

                <div class="dna-empty">
                    <h3>Your next meal starts here</h3>
                    <p>
                        Once meal planning is ready, your meals and portions
                        will appear here.
                    </p>
                </div>

                @foreach (['Breakfast', 'Lunch', 'Dinner', 'Snacks'] as $meal)
                    <div class="dna-meal">
                        <div class="dna-meal-icon" aria-hidden="true">
                            {{ substr($meal, 0, 1) }}
                        </div>

                        <div>
                            <h3>{{ $meal }}</h3>
                            <p>No meal planned.</p>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="dna-panel" aria-labelledby="progress-heading">
                <div class="dna-panel-header">
                    <h2 id="progress-heading">Your direction</h2>
                    <span class="dna-status">Getting started</span>
                </div>

                <div class="dna-empty">
                    <div class="dna-eyebrow">Small steps, steady habits</div>

                    <h3 style="margin-top: 12px;">Progress begins with you</h3>

                    <p>
                        Choose what you want to work towards.
                        Your progress will build from the information you record.
                    </p>
                </div>

                <div class="dna-progress">
                    <div class="dna-progress-row">
                        <span>Nutrition goal</span>
                        <strong>Not set</strong>
                    </div>

                    <div class="dna-progress-row">
                        <span>Weight records</span>
                        <strong>No entries</strong>
                    </div>

                    <div class="dna-progress-row">
                        <span>Food diary</span>
                        <strong>No entries</strong>
                    </div>
                </div>
            </section>
        </div>

        <div class="dna-section-heading">
            <h2>A plan that fits your life</h2>
            <span>Open a section to explore</span>
        </div>

        <section class="dna-explore" aria-label="Upcoming nutrition tools">
            <details id="profile-preview">
                <summary>Your nutrition profile</summary>
                <p>
                    This will bring together your goals, dietary preferences,
                    allergies and foods you prefer to avoid.
                    Profile editing is coming next.
                </p>
            </details>

            <details>
                <summary>Less planning, more variety</summary>
                <p>
                    Your meal plan will organise recipes and portions around
                    your saved preferences. Meal planning is coming soon.
                </p>
            </details>

            <details>
                <summary>A clearer shopping list</summary>
                <p>
                    Your shopping list will combine the ingredients needed
                    for your chosen meals. Shopping tools are coming soon.
                </p>
            </details>
        </section>

        <p class="dna-footer-note">
            Your dashboard is ready to grow with you.
            Nutrition and progress figures will appear when their tools are connected.
        </p>
    </div>
@endsection