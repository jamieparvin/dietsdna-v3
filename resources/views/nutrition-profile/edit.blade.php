@extends('layouts.app')

@section('title', 'Nutrition profile')

@section('content')
    @php
        $hasProfile = $profile !== null;

        $openSection = function (array $fields) use ($hasProfile, $errors) {
            if (! $hasProfile) {
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
        $dietText = $dietaryPreferences[$profile?->dietary_preference ?? ''] ?? 'Not set';

        $today = now()->timezone('Europe/London');
        $earliestBirthDate = $today->copy()->subYears(120)->toDateString();
        $latestBirthDate = $today->copy()->subYears(18)->toDateString();
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
            font-size: 12px;
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
        .pf-page input:not([type="hidden"]) {
            width: 100%;
            min-width: 0;
            padding: 13px;
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

        .pf-save-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .pf-save-actions a {
            font-size: 13px;
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
                    Your details, goals and food preferences.
                    Open a card to make changes.
                </p>
            </div>

            <div class="pf-saved">
                @if ($hasProfile)
                    Saved {{ $profile->updated_at->timezone('Europe/London')->format('j M Y, H:i') }}
                @else
                    Ready to personalise
                @endif
            </div>
        </header>

        <form
            method="POST"
            action="{{ route('nutrition-profile.update') }}"
            id="nutrition-profile-form"
        >
            @csrf
            @method('PUT')

            <div class="pf-grid">
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
                            <dd>{{ $formatMeasurement($profile?->weight_kg, 'kg') }}</dd>
                        </div>
                    </dl>

                    <p class="pf-note">
                        Your profile weight is a saved measurement.
                        Dated weight tracking will be added separately.
                    </p>

                    <details
                        class="pf-edit"
                        @if ($openSection(['date_of_birth', 'height_cm', 'weight_kg'])) open @endif
                    >
                        <summary>Edit personal details</summary>

                        <div class="pf-edit-body">
                            <div class="pf-fields">
                                <div class="pf-span">
                                    <label for="date_of_birth">Date of birth</label>

                                    <input
                                        id="date_of_birth"
                                        name="date_of_birth"
                                        type="date"
                                        autocomplete="bday"
                                        min="{{ $earliestBirthDate }}"
                                        max="{{ $latestBirthDate }}"
                                        value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}"
                                        aria-describedby="birth-date-help"
                                    >

                                    <p class="pf-note" id="birth-date-help">
                                        Optional. This nutrition profile is for adults aged 18 and over.
                                    </p>
                                </div>

                                <div>
                                    <label for="height_cm">Height (cm)</label>

                                    <input
                                        id="height_cm"
                                        name="height_cm"
                                        type="number"
                                        min="50"
                                        max="300"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="{{ old('height_cm', $profile?->height_cm) }}"
                                    >
                                </div>

                                <div>
                                    <label for="weight_kg">Profile weight (kg)</label>

                                    <input
                                        id="weight_kg"
                                        name="weight_kg"
                                        type="number"
                                        min="20"
                                        max="500"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="{{ old('weight_kg', $profile?->weight_kg) }}"
                                    >
                                </div>
                            </div>

                            <p class="pf-note">
                                Name and email are shown from your account.
                            </p>
                        </div>
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
                            <dd>{{ $formatMeasurement($profile?->target_weight_kg, 'kg') }}</dd>
                        </div>

                        <div>
                            <dt>Meals per day</dt>
                            <dd>{{ $profile?->meals_per_day ?? 'Not set' }}</dd>
                        </div>

                        <div class="pf-span">
                            <dt>Cooking time per meal</dt>
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
                            'meals_per_day',
                            'cooking_minutes',
                        ])) open @endif
                    >
                        <summary>Edit goals and activity</summary>

                        <div class="pf-edit-body">
                            <label for="goal">Main goal</label>

                            <select id="goal" name="goal">
                                <option value="">Choose your goal</option>

                                @foreach ($goals as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('goal', $profile?->goal) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>

                            <label for="activity_level">Activity level</label>

                            <select id="activity_level" name="activity_level">
                                <option value="">Choose your activity level</option>

                                @foreach ($activityLevels as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('activity_level', $profile?->activity_level) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>

                            <label for="target_weight_kg">Target weight (kg)</label>

                            <input
                                id="target_weight_kg"
                                name="target_weight_kg"
                                type="number"
                                min="20"
                                max="500"
                                step="0.01"
                                inputmode="decimal"
                                value="{{ old('target_weight_kg', $profile?->target_weight_kg) }}"
                                aria-describedby="target-weight-help"
                            >

                            <p class="pf-note" id="target-weight-help">
                                Optional. This records your personal goal;
                                it is not a recommended target.
                            </p>

                            <div class="pf-fields">
                                <div>
                                    <label for="meals_per_day">Preferred meals per day</label>

                                    <select id="meals_per_day" name="meals_per_day">
                                        <option value="">Choose your preference</option>

                                        @foreach (range(2, 6) as $number)
                                            <option
                                                value="{{ $number }}"
                                                @selected((string) old('meals_per_day', $profile?->meals_per_day) === (string) $number)
                                            >
                                                {{ $number }} meals
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="cooking_minutes">Cooking time per meal</label>

                                    <select id="cooking_minutes" name="cooking_minutes">
                                        <option value="">Choose your preference</option>

                                        @foreach ([15, 30, 45, 60] as $minutes)
                                            <option
                                                value="{{ $minutes }}"
                                                @selected((string) old('cooking_minutes', $profile?->cooking_minutes) === (string) $minutes)
                                            >
                                                {{ $minutes }} minutes
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </details>
                </section>

                <section class="pf-card" aria-labelledby="diet-heading">
                    <div class="pf-card-head">
                        <span class="pf-number" aria-hidden="true">03</span>

                        <div>
                            <h2 id="diet-heading">Dietary preferences</h2>
                            <p class="pf-description">Your preferred way of eating</p>
                        </div>
                    </div>

                    <span class="pf-preference">{{ $dietText }}</span>

                    <p class="pf-note">
                        Your dietary preference is separate from allergies,
                        intolerances and individual foods you avoid.
                    </p>

                    <details
                        class="pf-edit"
                        @if ($openSection(['dietary_preference'])) open @endif
                    >
                        <summary>Edit dietary preference</summary>

                        <div class="pf-edit-body">
                            <label for="dietary_preference">Dietary preference</label>

                            <select id="dietary_preference" name="dietary_preference">
                                <option value="">Choose your preference</option>

                                @foreach ($dietaryPreferences as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('dietary_preference', $profile?->dietary_preference) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </details>
                </section>

                <section class="pf-card" aria-labelledby="allergies-heading">
                    <div class="pf-card-head">
                        <span class="pf-number" aria-hidden="true">04</span>

                        <div>
                            <h2 id="allergies-heading">Allergies and restrictions</h2>
                            <p class="pf-description">Foods you need or prefer to avoid</p>
                        </div>
                    </div>

                    <dl class="pf-list">
                        <div class="pf-span">
                            <dt>Food allergies</dt>
                            <dd>{{ filled($profile?->allergies) ? $profile->allergies : 'None recorded' }}</dd>
                        </div>

                        <div class="pf-span">
                            <dt>Other exclusions or intolerances</dt>
                            <dd>{{ filled($profile?->foods_to_avoid) ? $profile->foods_to_avoid : 'None recorded' }}</dd>
                        </div>
                    </dl>

                    <p class="pf-note">
                        These details are saved as notes.
                        Automatic allergy filtering is not available yet.
                    </p>

                    <details
                        class="pf-edit"
                        @if ($openSection(['allergies', 'foods_to_avoid'])) open @endif
                    >
                        <summary>Edit allergies and restrictions</summary>

                        <div class="pf-edit-body">
                            <label for="allergies">Food allergies</label>

                            <textarea
                                id="allergies"
                                name="allergies"
                                maxlength="2000"
                                placeholder="For example: peanuts, sesame"
                            >{{ old('allergies', $profile?->allergies) }}</textarea>

                            <label for="foods_to_avoid">
                                Other exclusions or intolerances
                            </label>

                            <textarea
                                id="foods_to_avoid"
                                name="foods_to_avoid"
                                maxlength="2000"
                                placeholder="Foods you dislike or do not tolerate"
                            >{{ old('foods_to_avoid', $profile?->foods_to_avoid) }}</textarea>
                        </div>
                    </details>
                </section>
            </div>

            <footer class="pf-save">
                <p class="pf-note">
                    All fields are optional.
                    Save your changes to update the summaries above.
                </p>

                <div class="pf-save-actions">
                    <a href="{{ route('dashboard') }}">Back to dashboard</a>

                    <button type="submit">Save nutrition profile</button>
                </div>
            </footer>
        </form>
    </div>

    <script>
        (() => {
            const form = document.getElementById('nutrition-profile-form');

            form.addEventListener('invalid', (event) => {
                const editor = event.target.closest('details');

                if (editor) {
                    editor.open = true;
                }
            }, true);
        })();
    </script>
@endsection