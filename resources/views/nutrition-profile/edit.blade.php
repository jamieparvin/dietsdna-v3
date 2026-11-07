@extends('layouts.app')

@section('title', 'Nutrition profile')

@section('content')
    @if(!$setup['complete'] || request()->routeIs('weekly-meals.create'))
        @include('onboarding-progress')
    @endif
    @php
        $hasProfile = $profile !== null;
        $isProfileSetup = ! $setup['complete'];

        $openSection = function (array $fields) use ($hasProfile, $errors, $setup) {
            if (! $hasProfile || ! $setup['profileComplete']) {
                return true;
            }

            foreach ($fields as $field) {
                if ($errors->has($field)) {
                    return true;
                }
            }

            return false;
        };

        $formatMeasurement = function ($value, string $unit) {
            return $value !== null
                ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').' '.$unit
                : 'Not set';
        };

        $goalText = $goals[$profile?->goal ?? ''] ?? 'Not set';
        $activityText = $activityLevels[$profile?->activity_level ?? ''] ?? 'Not set';

        $today = now()->timezone('Europe/London');
        $earliestBirthDate = $today->copy()->subYears(120)->toDateString();
        $latestBirthDate = $today->copy()->subYears(18)->toDateString();

        $snackCount = (int) ($profile?->snacks_per_day ?? 0);

        $patternText = $profile?->meals_per_day
            ? $profile->meals_per_day.' full meals · '.(
                $snackCount === 0
                    ? 'No snacks'
                    : $snackCount.' optional '.($snackCount === 1 ? 'snack' : 'snacks')
            )
            : 'Not set';

        $hasRestrictions = filled($profile?->allergies)
            || filled($profile?->foods_to_avoid);
    @endphp

    <style>
        .pf-page {
            width: 100%;
            color: #153f32;
        }

        .pf-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 26px;
        }

        .pf-eyebrow {
            margin-bottom: 10px;
            color: #64816e;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
        }

        .pf-heading h1 {
            margin-bottom: 10px;
            font-size: clamp(28px, 3vw, 38px);
            letter-spacing: -1.2px;
        }

        .pf-heading p {
            margin: 0;
            font-size: 14px;
            line-height: 1.7;
        }

        .pf-saved {
            padding: 9px 14px;
            background: #e9f3ec;
            border: 1px solid #d1e4d6;
            border-radius: 999px;
            color: #356249;
            font-size: 12px;
        }

        .pf-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .pf-setup-grid {
            grid-template-columns: 1fr;
        }

        .pf-setup-grid .pf-edit-body {
            padding: 0;
        }

        .pf-field-hint {
            color: #53675e;
            font-weight: 400;
        }

        .pf-card {
            display: flex;
            flex-direction: column;
            gap: 18px;
            min-width: 0;
            padding: 28px;
            background: white;
            border: 1px solid #dfe7df;
            border-radius: 18px;
        }

        .pf-card-head {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .pf-number {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            background: #edf4e9;
            border-radius: 11px;
            color: #4f7255;
            font-size: 12px;
            font-weight: 700;
        }

        .pf-card h2 {
            margin: 0;
            font-size: 19px;
            letter-spacing: -.3px;
        }

        .pf-description {
            margin: 4px 0 0;
            color: #6b786f;
            font-size: 12px;
            line-height: 1.6;
        }

        .pf-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 22px;
            margin: 0;
        }

        .pf-list > div {
            min-width: 0;
            padding: 13px 0;
            border-bottom: 1px solid #edf0e8;
        }

        .pf-list dt {
            color: #6b786f;
            font-size: 12px;
        }

        .pf-list dd {
            margin: 6px 0 0;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.5;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .pf-list dd.pf-text {
            white-space: pre-line;
        }

        .pf-span {
            grid-column: 1 / -1;
        }

        .pf-note {
            margin: 0;
            color: #6b786f;
            font-size: 12px;
            line-height: 1.7;
        }

        .pf-help-box {
            padding: 16px 18px;
            background: #f3f7ee;
            border: 1px solid #e0e8d8;
            border-radius: 12px;
        }

        .pf-help-box strong {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .pf-warning {
            padding: 16px 18px;
            background: #fff8e9;
            border: 1px solid #ead9af;
            border-radius: 12px;
            color: #705725;
            font-size: 12px;
            line-height: 1.7;
        }

        .pf-warning strong {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .pf-edit {
            margin-top: auto;
            background: #fafbf7;
            border: 1px solid #dfe7df;
            border-radius: 12px;
        }

        .pf-edit > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 48px;
            padding: 15px 17px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            list-style: none;
        }

        .pf-edit > summary::-webkit-details-marker {
            display: none;
        }

        .pf-edit > summary::after {
            content: '+';
            font-size: 20px;
            font-weight: 400;
        }

        .pf-edit[open] > summary::after {
            content: '−';
        }

        .pf-edit[open] > summary {
            border-bottom: 1px solid #e5ebe0;
        }

        .pf-edit-body {
            padding: 0 17px 20px;
        }

        .pf-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 18px;
        }

        .pf-fields > div {
            min-width: 0;
        }

        .pf-page label {
            font-size: 13px;
        }

        .pf-page select,
        .pf-page textarea,
        .pf-page input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]) {
            width: 100%;
            min-width: 0;
            min-height: 46px;
            padding: 13px;
            box-sizing: border-box;
            background: white;
            border: 1px solid #b9cbc0;
            border-radius: 8px;
            color: #17332c;
            font: inherit;
        }

        .pf-page textarea {
            min-height: 110px;
            resize: vertical;
        }

        .pf-edit-body .pf-note {
            margin-top: 10px;
        }

        .pf-preference {
            display: inline-block;
            align-self: flex-start;
            padding: 9px 15px;
            background: #edf5e6;
            border: 1px solid #d2e3c4;
            border-radius: 999px;
            color: #315c39;
            font-size: 14px;
            font-weight: 600;
        }

        .pf-link {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            min-height: 44px;
            font-size: 13px;
            font-weight: 700;
            color: #205b43;
        }

        .pf-errors {
            margin-bottom: 22px;
            padding: 18px 22px;
            background: #fff2ee;
            border: 1px solid #edcfc5;
            border-radius: 12px;
            color: #8a382b;
            font-size: 13px;
            line-height: 1.7;
        }

        .pf-errors ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .pf-save {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 24px;
            padding: 22px 26px;
            background: white;
            border: 1px solid #dfe7df;
            border-radius: 16px;
        }

        .pf-save > .guidance-estimate {
            flex: 1 1 300px;
        }

        .pf-save-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .pf-save-actions a {
            font-size: 13px;
        }

        .pf-save-actions button {
            min-height: 46px;
        }

        .pf-page summary:focus-visible,
        .pf-page a:focus-visible,
        .pf-page button:focus-visible,
        .pf-page input:focus-visible,
        .pf-page select:focus-visible,
        .pf-page textarea:focus-visible {
            outline: 3px solid #9fbea9;
            outline-offset: 3px;
        }

        .pf-fields > .pf-span {
            min-width: 0;
        }

        .pf-fields input#date_of_birth {
            -webkit-appearance: none;
            appearance: none;
            display: block;
            box-sizing: border-box;
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        .pf-fields input#date_of_birth::-webkit-date-and-time-value {
            min-width: 0;
            margin: 0;
            text-align: left;
        }

        @media (max-width: 1050px) {
            .pf-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .pf-card {
                padding: 20px;
            }

            .pf-list,
            .pf-fields {
                grid-template-columns: 1fr;
            }

            .pf-save {
                align-items: stretch;
                flex-direction: column;
                padding: 20px;
            }

            .pf-save > .guidance-estimate {
                flex: auto;
            }

            .pf-save-actions {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>

    <div class="pf-page">
        <header class="pf-heading">
            <div>
                <div class="pf-eyebrow">Made around you</div>
                <h1>Your nutrition profile</h1>

                <p>
                    Your measurements, goals and meal pattern.
                    {{ $isProfileSetup ? 'Complete the fields below. You can save your progress at any time.' : 'Open a card to make changes.' }}
                </p>
            </div>

            <div class="pf-saved">
                @if($isProfileSetup)
                    {{ !$hasProfile ? 'Profile not started' : (!$setup['profileComplete'] ? 'Profile setup in progress' : ($targets['status'] === 'review' ? 'Details saved - automatic planning unavailable' : 'Profile saved - preferences next')) }}
                @elseif($profile?->updated_at)
                    Saved {{ $profile->updated_at->timezone('Europe/London')->format('j M Y, H:i') }}
                @endif
            </div>
        </header>

        @include('partials.food-allergy-notice', ['restrictionMessage' => $hasRestrictions ? 'Automatic meal generation is unavailable because your account has a recorded allergy or food restriction.' : null])

        <form
            method="POST"
            action="{{ route('nutrition-profile.update') }}"
            id="nutrition-profile-form"
        >
            @csrf
            @method('PUT')

            <div class="pf-grid {{ $isProfileSetup ? 'pf-setup-grid' : '' }}">
                @if($isProfileSetup)
                    @include('nutrition-profile.setup-fields')
                @else
                    <section class="pf-card" aria-labelledby="personal-heading">
                        <div class="pf-card-head">
                            <span class="pf-number" aria-hidden="true">01</span>

                            <div>
                                <h2 id="personal-heading">Personal details</h2>
                                <p class="pf-description">Your account and measurements</p>
                            </div>
                        </div>

                        <dl class="pf-list">
                            <div>
                                <dt>Name</dt>
                                <dd>{{ auth()->user()->name }}</dd>
                            </div>

                            <div>
                                <dt>Email address</dt>
                                <dd>{{ auth()->user()->email }}</dd>
                            </div>

                            <div>
                                <dt>Date of birth</dt>
                                <dd>{{ $profile?->date_of_birth?->format('j M Y') ?? 'Not set' }}</dd>
                            </div>

                            <div>
                                <dt>Height</dt>
                                <dd>{{ $formatMeasurement($profile?->height_cm, 'cm') }}</dd>
                            </div>

                            <div>
                                <dt>Profile weight</dt>
                                <dd>{{ \App\Services\Nutrition\WeightUnits::format($profile?->weight_kg).($profile?->weight_kg !== null ? ' lbs' : '') }}</dd>
                            </div>
                            <div>
                                <dt>BMI · profile weight</dt>
                                <dd data-bmi-value>{{ $bmi['value'] === null ? 'Not available' : number_format($bmi['value'], 1) }}</dd>
                            </div>
                        </dl>

                        @include('partials.bmi-context')

                        <p class="pf-note">
                            Your profile weight is used to calculate your nutrition guide.
                            Record dated weigh-ins in
                            <a href="{{ route('progress.index') }}">Your progress</a>.
                            Weigh-ins do not automatically update this profile weight.
                        </p>

                        <details
                            class="pf-edit"
                            @if ($openSection(['date_of_birth', 'height_cm', 'weight_kg', 'weight_lbs'])) open @endif
                        >
                            <summary>Edit personal details</summary>

                            @include('nutrition-profile.personal-fields')
                        </details>
                    </section>

                    <section class="pf-card" aria-labelledby="goals-heading">
                        <div class="pf-card-head">
                            <span class="pf-number" aria-hidden="true">02</span>

                            <div>
                                <h2 id="goals-heading">Goals and activity</h2>
                                <p class="pf-description">Your direction and daily routine</p>
                            </div>
                        </div>

                        <dl class="pf-list">
                            <div class="pf-span">
                                <dt>Primary goal</dt>
                                <dd>{{ $goalText }}</dd>
                            </div>

                            <div class="pf-span">
                                <dt>Activity level</dt>
                                <dd>{{ $activityText }}</dd>
                            </div>

                            <div>
                                <dt>Target weight</dt>
                                <dd>{{ \App\Services\Nutrition\WeightUnits::format($profile?->target_weight_kg).($profile?->target_weight_kg !== null ? ' lbs' : '') }}</dd>
                            </div>

                            <div>
                                <dt>Daily meal pattern</dt>
                                <dd>{{ $patternText }}</dd>
                            </div>

                            <div class="pf-span">
                                <dt>Time available per meal</dt>
                                <dd>{{ $profile?->cooking_minutes !== null ? $profile->cooking_minutes.' minutes' : 'Not set' }}</dd>
                            </div>
                        </dl>

                        <p class="pf-note">
                            Choose the descriptions that best reflect your goals
                            and usual routine.
                        </p>

                        <details
                            class="pf-edit"
                            @if ($openSection([
                                'goal',
                                'activity_level',
                                'target_weight_kg',
                                'target_weight_lbs',
                                'meals_per_day',
                                'snacks_per_day',
                                'cooking_minutes',
                            ])) open @endif
                        >
                            <summary>Edit goals and activity</summary>

                            @include('nutrition-profile.goal-fields')
                            @include('nutrition-profile.meal-pattern-fields')
                        </details>
                    </section>

                    @include('nutrition-profile.calculation-details')
                    @include('nutrition-profile.targets')
                @endif
            </div>

            <footer class="pf-save">
                @include('partials.nutrition-estimate-disclaimer')

                <div class="pf-save-actions">
                    <a href="{{ route('dashboard') }}">Back to dashboard</a>
                    <button type="submit">{{ $setup['complete'] ? 'Save nutrition profile' : 'Save and continue' }}</button>
                </div>
            </footer>
        </form>

        @if(!$isProfileSetup)
            @include('nutrition-profile.macros')
        @endif
    </div>

    <script>
        (() => {
            const form = document.getElementById('nutrition-profile-form');

            if (!form) {
                return;
            }

            form.addEventListener('invalid', (event) => {
                const editor = event.target.closest('details');

                if (editor) {
                    editor.open = true;
                }
            }, true);
        })();
    </script>
@endsection