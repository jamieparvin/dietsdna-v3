<?php

namespace App\Services\Meals;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Read-only checks of historical snapshots against today's explicit food groups. */
class MealFoodExclusions
{
    public function identities(User $user): array
    {
        $keys = DB::table('user_food_preferences')->where('user_id', $user->id)
            ->where('preference', 'excluded')->pluck('food_key')->all();
        $foods = Ingredient::query()->whereIn('preference_group_key', $keys)
            ->orWhereIn('preference_key', $keys)->get(['id', 'preference_key']);

        return [
            'ids' => array_fill_keys($foods->pluck('id')->all(), true),
            'keys' => array_fill_keys(array_merge($keys, $foods->pluck('preference_key')->all()), true),
        ];
    }

    public function names(array $snapshot, array $identities): array
    {
        $names = [];
        foreach ($snapshot['ingredients'] ?? [] as $row) {
            if (isset($identities['ids'][$row['ingredient_id'] ?? ''])
                || isset($identities['keys'][$row['food_key'] ?? ''])) {
                $names[] = $row['name'] ?? 'Excluded ingredient';
            }
        }

        return array_values(array_unique($names));
    }
}
