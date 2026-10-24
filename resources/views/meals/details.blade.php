<h3>{{ $meal['name'] }}</h3>

<p class="mp-muted">{{ $meal['description'] }}</p>

<div class="mp-nutrition">
    {{ number_format($meal['totals']['energy_kcal']) }} kcal
    · {{ number_format($meal['totals']['protein_g'], 1) }} g protein
    · {{ number_format($meal['totals']['carbohydrate_g'], 1) }} g total carbs
    · {{ number_format($meal['totals']['fat_g'], 1) }} g fat
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