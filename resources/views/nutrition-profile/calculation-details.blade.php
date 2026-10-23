<section class="pf-card" aria-labelledby="calculation-heading">
    <div class="pf-card-head">
        <span class="pf-number" aria-hidden="true">05</span>

        <div>
            <h2 id="calculation-heading">Planning details</h2>
            <p class="pf-description">
                Information used for your calorie and macro estimates
            </p>
        </div>
    </div>

    <dl class="pf-list">
        <div>
            <dt>Calculation equation</dt>
            <dd>
                {{ match ($profile?->calculation_sex) {
                    'male' => 'Male equation',
                    'female' => 'Female equation',
                    default => 'Not set',
                } }}
            </dd>
        </div>

        <div>
            <dt>Resistance training</dt>
            <dd>
                {{ $profile?->resistance_training === null
                    ? 'Not set'
                    : ($profile->resistance_training ? 'Yes' : 'No') }}
            </dd>
        </div>

        <div class="pf-span">
            <dt>Planning suitability</dt>
            <dd>
                {{ match ($profile?->planning_context) {
                    'general_adult' => 'General adult planning',
                    'needs_review' => 'Individual review needed',
                    default => 'Not set',
                } }}
            </dd>
        </div>
    </dl>

    <details
        class="pf-edit"
        @if (
            ! $profile?->calculation_sex
            || ! $profile?->planning_context
            || $profile?->resistance_training === null
            || $errors->hasAny([
                'calculation_sex',
                'planning_context',
                'resistance_training',
            ])
        ) open @endif
    >
        <summary>Edit planning details</summary>

        <div class="pf-edit-body">
            <label for="calculation_sex">Sex used by the calorie equation</label>

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

            <label for="planning_context">Which description applies?</label>

            <select id="planning_context" name="planning_context">
                <option value="">Choose an answer</option>

                <option
                    value="general_adult"
                    @selected(old('planning_context', $profile?->planning_context) === 'general_adult')
                >
                    Adult, not pregnant or breastfeeding, no specialist dietary needs
                </option>

                <option
                    value="needs_review"
                    @selected(old('planning_context', $profile?->planning_context) === 'needs_review')
                >
                    Pregnant, breastfeeding, medical dietary needs, or unsure
                </option>
            </select>

            <p class="pf-note">
                Choose individual review if a condition, medication, eating
                disorder history or unexplained weight change affects your
                dietary needs.
            </p>
        </div>
    </details>
</section>