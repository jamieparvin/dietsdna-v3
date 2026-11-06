<div class="pf-edit-body">
    <div class="pf-fields">
        <div>
            <label for="meals_per_day">Full meals per day <span class="pf-field-hint">(Required for setup)</span></label>

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

            <p class="pf-note">
                Choose your number of full meals.
                Snacks are selected separately.
            </p>
        </div>

        <div>
            <label for="cooking_minutes">Time available per meal <span class="pf-field-hint">(Optional)</span></label>

            <select id="cooking_minutes" name="cooking_minutes" aria-describedby="meal-time-help">
                <option value="">Choose your preference</option>

                @foreach (\App\Services\Meals\MealPreparationTime::allowed($profile?->cooking_minutes) as $minutes)
                    <option
                        value="{{ $minutes }}"
                        @selected((string) old('cooking_minutes', $profile?->cooking_minutes) === (string) $minutes)
                    >
                        {{ $minutes }} minutes{{ in_array($minutes, [45, 60], true) ? ' (previously saved)' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="pf-note" id="meal-time-help">Choose how much time you’d usually like to spend preparing and cooking each meal.</p>
            @if(in_array($profile?->cooking_minutes, [45, 60], true))
                <p class="pf-note">Your previously saved {{ $profile->cooking_minutes }} minutes remains in use until you choose another option or leave this blank.</p>
            @endif
        </div>

        <div class="pf-span">
            <label for="snacks_per_day">Optional snacks <span class="pf-field-hint">(Optional)</span></label>

            <select
                id="snacks_per_day"
                name="snacks_per_day"
                aria-describedby="snacks-help"
            >
                @foreach ([0 => 'No snacks', 1 => '1 snack per day', 2 => '2 snacks per day'] as $value => $label)
                    <option
                        value="{{ $value }}"
                        @selected((string) old('snacks_per_day', $profile?->snacks_per_day ?? 0) === (string) $value)
                    >
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <p class="pf-note" id="snacks-help">
                Snacks share your daily calorie and macro budget.
                They do not increase your daily targets.
            </p>
        </div>
    </div>
</div>
