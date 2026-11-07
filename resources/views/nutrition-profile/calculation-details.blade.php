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

    </dl>

    <details
        class="pf-edit"
        @if (
            ! $profile?->calculation_sex
            || $profile?->resistance_training === null
            || $errors->hasAny([
                'calculation_sex',
                'resistance_training',
            ])
        ) open @endif
    >
        <summary>Edit planning details</summary>

        @include('nutrition-profile.calculation-fields')
    </details>
</section>