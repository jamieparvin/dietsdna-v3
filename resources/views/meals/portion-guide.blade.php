@php
    $portionGuide = collect($day['rows'])
        ->map(fn ($row) => $row['snapshot']['portion_balance'] ?? null)
        ->filter()
        ->sortByDesc(fn ($guide) => ($guide['version'] ?? null) === \App\Services\Meals\MealPortionBalancer::VERSION)
        ->first();
    $estimatedAvailableCarbs = collect($day['rows'])->sum(
        fn ($row) => max(0,
            (float) ($row['snapshot']['totals']['carbohydrate_g'] ?? 0)
            - (float) ($row['snapshot']['totals']['fibre_g'] ?? 0)
        )
    );
@endphp

@if($portionGuide)
    <div class="wm-notice" style="margin-bottom:16px; line-height:1.7;">
        <strong>Daily guide</strong><br>
        {{ number_format($portionGuide['daily_targets']['calories_kcal']) }} kcal ·
        {{ number_format($portionGuide['daily_targets']['protein_g'], 1) }} g protein ·
        {{ number_format($portionGuide['daily_targets']['carbohydrate_g'], 1) }} g estimated available carbs ·
        {{ number_format($portionGuide['daily_targets']['fat_g'], 1) }} g fat.
        <br>
        This day contains {{ number_format($estimatedAvailableCarbs, 1) }} g estimated available carbs.
        Total carbs shown on the cards include fibre; the estimate subtracts fibre for comparison with the guide.
        <details>
            <summary style="cursor:pointer; min-height:44px; padding-top:10px;">How portions were checked</summary>
            New portions were checked against database calories (within 5%) and each macro guide
            (within 10%), using the estimated carbohydrate basis above.
            @if(($portionGuide['version'] ?? null) === \App\Services\Meals\MealPortionBalancer::VERSION)
                <br>Full meals aim for equal shares of the remaining budget. Each optional snack
                starts with a 10% daily weight. Existing meals are subtracted first.
                New full meals are checked within 25% of their calorie share and 30% of their
                protein share. Snacks are checked within 35% of their calorie share;
                their protein can vary. Carbohydrate and fat shares guide the adjustment,
                while their limits are checked across the day.
            @endif
            <br>These are planning tolerances, not exact nutrition measurements.
            Existing planned portions were preserved. Swapping meals later can change these totals.
        </details>
    </div>
@endif
