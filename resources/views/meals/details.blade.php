<h3>{{ $meal['name'] }}</h3>

<p class="mp-muted">{{ $meal['description'] }}</p>

<div class="mp-nutrition">
    {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['energy_kcal'] ?? null, 0) }} kcal
    · {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['protein_g'] ?? null, 1) }} g protein
    · {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['carbohydrate_g'] ?? null, 1) }} g total carbs
    · {{ \App\Services\Nutrition\SnapshotNutrition::format($meal['totals']['fat_g'] ?? null, 1) }} g fat
</div>

<details>
    <summary>Ingredients and preparation</summary>

    <ul class="mp-ingredients">
        @foreach($meal['ingredients'] as $ingredient)
            <li>
                <div>
                    {{ $ingredient['name'] }}
                    <small>{{ $ingredient['preparation_state'] }}</small>
                </div>
                <strong>
                    {{ number_format($ingredient['quantity_g'], 1) }} g
                </strong>
            </li>
        @endforeach
    </ul>

    <div class="mp-method">{{ $meal['instructions'] }}</div>
</details>