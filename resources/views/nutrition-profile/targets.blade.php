<section class="pf-card" aria-labelledby="nutrition-targets-heading">
    <div class="pf-card-head">
        <span class="pf-number" aria-hidden="true">06</span>
        <div>
            <h2 id="nutrition-targets-heading">Calories and macro estimates</h2>
            <p class="pf-description">Starting guides from your saved profile</p>
        </div>
    </div>
    @if(($targets['status'] ?? '') === 'ready')
        <dl class="pf-list">
            <div><dt>Daily calories</dt><dd>{{ number_format($targets['calories_kcal']) }} kcal</dd></div>
            <div><dt>Protein · {{ number_format($targets['macro_percentages']['protein'], 2) }}%</dt><dd>{{ number_format($targets['protein_g'], 1) }} g</dd></div>
            <div><dt>Carbohydrate allocation · {{ number_format($targets['macro_percentages']['carbohydrate'], 2) }}%</dt><dd>{{ number_format($targets['carbohydrate_g'], 1) }} g</dd></div>
            <div><dt>Fat · {{ number_format($targets['macro_percentages']['fat'], 2) }}%</dt><dd>{{ number_format($targets['fat_g'], 1) }} g</dd></div>
        </dl>
        <p class="pf-note">
            {{ $targets['macro_source'] === 'custom' ? 'Using your saved custom split.' : 'Using the default split for your goal.' }}
            Food total carbohydrate includes fibre and differs from this energy allocation.
            Actual food nutrition is calculated separately from ingredient data.
        </p>
        <a href="#macro-targets">See how these are calculated and adjust your split</a>
    @else
        <p class="pf-note">{{ $targets['message'] ?? 'Complete your profile to see your starting guides.' }}</p>
        @if(($profile?->macro_mode ?? 'goal') === 'custom')
            <a href="#macro-targets">Review your macro preferences</a>
        @endif
    @endif
</section>
