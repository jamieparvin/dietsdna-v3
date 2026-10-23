<section class="pf-card" aria-labelledby="targets-heading">
    <div class="pf-card-head">
        <span class="pf-number" aria-hidden="true">06</span>

        <div>
            <h2 id="targets-heading">Daily calorie and macro targets</h2>
            <p class="pf-description">Calculated from your saved details</p>
        </div>
    </div>

    @if ($targets['status'] === 'ready')
        <dl class="pf-list">
            <div>
                <dt>Daily energy</dt>
                <dd>{{ number_format($targets['calories_kcal']) }} kcal</dd>
            </div>

            <div>
                <dt>Protein</dt>
                <dd>{{ number_format($targets['protein_g'], 1) }} g</dd>
            </div>

            <div>
                <dt>Fat</dt>
                <dd>{{ number_format($targets['fat_g'], 1) }} g</dd>
            </div>

            <div>
                <dt>Carbohydrate allocation*</dt>
                <dd>{{ number_format($targets['carbohydrate_g'], 1) }} g</dd>
            </div>
        </dl>

        <p class="pf-note">
            These are starting estimates, not measured requirements.
            Review them against your weight trend and actual intake over time.
            Saving a different goal or measurement recalculates the targets.
        </p>

        <details class="pf-edit">
            <summary>How these numbers are calculated</summary>

            <div class="pf-edit-body">
                <p class="pf-note">
                    Resting energy: {{ number_format($targets['resting_kcal']) }} kcal.
                    Mifflin–St Jeor equation using age {{ $targets['age'] }}.
                </p>

                <p class="pf-note">
                    Activity multiplier: {{ $targets['activity_factor'] }}.
                    Estimated maintenance:
                    {{ number_format($targets['maintenance_kcal']) }} kcal.
                    Activity includes usual exercise; exercise calories are not
                    added again.
                </p>

                <p class="pf-note">
                    Goal adjustment:
                    {{ $targets['adjustment_kcal'] >= 0 ? '+' : '' }}{{ number_format($targets['adjustment_kcal']) }}
                    kcal after rounding.
                    Weight loss uses a 15% deficit; weight gain adds 300 kcal;
                    muscle building adds 200 kcal.
                </p>

                <p class="pf-note">
                    Protein: {{ $targets['protein_factor'] }} g per kg of current
                    weight. Fat: 30% of calories. The remaining energy is
                    allocated to carbohydrate using a simplified 4/4/9 calculation.
                </p>

                <p class="pf-note">
                    *This carbohydrate allocation is not directly equivalent to
                    your ingredient library’s total carbohydrate, which includes
                    fibre. Recipe calories use stored ingredient energy values.
                    Fibre accounting must be reconciled when building the meal planner.
                </p>

                <p class="pf-note">
                    Choosing “build muscle” assumes strength training is part of
                    your plan; a calorie surplus alone does not ensure muscle gain.
                    No goal completion date is predicted.
                </p>

                <p class="pf-note">
                    Calculation version: {{ $targets['version'] }}.
                </p>
            </div>
        </details>
    @else
        <p class="pf-note">{{ $targets['message'] }}</p>

        @if ($targets['status'] === 'review')
            <p class="pf-note">
                You can still save your profile. Automatic targets remain
                unavailable until an appropriate individual plan is established.
            </p>
        @endif
    @endif
</section>