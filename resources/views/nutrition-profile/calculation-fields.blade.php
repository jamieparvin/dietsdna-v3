<div class="pf-edit-body">
    <label for="calculation_sex">Gender - for calorie equation <span class="pf-field-hint">(Required for setup)</span></label>

    <select id="calculation_sex" name="calculation_sex">
        <option value="">Choose an equation</option>
        <option
            value="female"
            @selected(old('calculation_sex', $profile?->calculation_sex) === 'female')
        >Female</option>
        <option
            value="male"
            @selected(old('calculation_sex', $profile?->calculation_sex) === 'male')
        >Male</option>
    </select>

    <p class="pf-note">
        This selects a coefficient in the resting-energy equation.
        If neither equation is appropriate, leave it blank and seek
        an individual estimate.
    </p>

    <label for="resistance_training">
        Do you regularly do resistance or strength training?
        <span class="pf-field-hint">(Required for setup)</span>
    </label>

    <select id="resistance_training" name="resistance_training">
        <option value="">Choose an answer</option>

        <option
            value="1"
            @selected((string) old(
                'resistance_training',
                $profile?->resistance_training === null
                    ? ''
                    : (int) $profile->resistance_training
            ) === '1')
        >Yes</option>

        <option
            value="0"
            @selected((string) old(
                'resistance_training',
                $profile?->resistance_training === null
                    ? ''
                    : (int) $profile->resistance_training
            ) === '0')
        >No</option>
    </select>

</div>
