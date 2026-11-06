<div class="pf-edit-body">
    @if(!$isProfileSetup)
        <p class="pf-note">Name and email are shown from your account.</p>
    @endif
    <div class="pf-fields">
        <div class="pf-span">
            <label for="date_of_birth">Date of birth <span class="pf-field-hint">(Required for setup)</span></label>

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
                This nutrition profile is for adults aged 18 and over.
            </p>
        </div>

        <div>
            <label for="height_cm">Height (cm) <span class="pf-field-hint">(Required for setup)</span></label>

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
            <label for="weight_lbs">Profile weight (lbs) <span class="pf-field-hint">(Required for setup)</span></label>

            <input
                id="weight_lbs"
                name="weight_lbs"
                type="number"
                min="{{ \App\Services\Nutrition\WeightUnits::inputLimit(20) }}"
                max="{{ \App\Services\Nutrition\WeightUnits::inputLimit(500) }}"
                step="any"
                inputmode="decimal"
                value="{{ \App\Services\Nutrition\WeightUnits::oldInput('weight_lbs', 'weight_kg', $profile?->weight_kg) }}"
            >
        </div>
    </div>
</div>
