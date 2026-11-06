<div class="pf-edit-body">
    <label for="goal">Main goal <span class="pf-field-hint">(Required for setup)</span></label>

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

    <label for="activity_level">Activity level <span class="pf-field-hint">(Required for setup)</span></label>

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

    <label for="target_weight_lbs">Target weight (lbs) <span class="pf-field-hint">(Optional)</span></label>

    <input
        id="target_weight_lbs"
        name="target_weight_lbs"
        type="number"
        min="{{ \App\Services\Nutrition\WeightUnits::inputLimit(20) }}"
        max="{{ \App\Services\Nutrition\WeightUnits::inputLimit(500) }}"
        step="any"
        inputmode="decimal"
        value="{{ \App\Services\Nutrition\WeightUnits::oldInput('target_weight_lbs', 'target_weight_kg', $profile?->target_weight_kg) }}"
        aria-describedby="target-weight-help"
    >

    <p class="pf-note" id="target-weight-help">
        Optional. Add a weight goal you have chosen for yourself. DietsDNA does not assess whether this goal is suitable for you.
    </p>
</div>
