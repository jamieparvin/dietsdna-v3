<?php

namespace App\Services\Meals;

use App\Models\MealPlanEntry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PlannedMealSnapshot
{
    public function fromIdea(array $idea, Collection $ingredients): array
    {
        unset($idea['calorie_difference']);

        foreach ($idea['ingredients'] as &$row) {
            $matches = $ingredients->filter(fn ($ingredient) => $ingredient->name === $row['name']
                && $ingredient->preparation_state === $row['preparation_state']
            );

            if ($matches->count() !== 1) {
                throw ValidationException::withMessages([
                    'generation' => 'An ingredient could not be identified uniquely. Check the ingredient library.',
                ]);
            }

            $ingredient = $matches->first();
            $row['ingredient_id'] = (int) $ingredient->id;
            $row['food_key'] = MealIdeaGenerator::foodKey($ingredient);
        }
        unset($row);

        return $idea;
    }

    public function eligible(array $snapshot, Collection $ingredients): bool
    {
        $catalog = $ingredients->keyBy('id');

        if (empty($snapshot['ingredients'])) {
            return false;
        }

        foreach ($snapshot['ingredients'] as $row) {
            $ingredient = $catalog->get($row['ingredient_id'] ?? 0);

            if (! $ingredient || MealIdeaGenerator::foodKey($ingredient) !== ($row['food_key'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Recipe matching ignores titles, nutrition totals and ingredient quantities. */
    public function recipeIdentity(array $snapshot): ?string
    {
        if (isset($snapshot['recipe_id']) && is_scalar($snapshot['recipe_id'])) {
            return hash('sha256', 'recipe:'.$snapshot['recipe_id']);
        }
        $ingredients = $this->recipeIngredients($snapshot);
        if ($ingredients === null) {
            return null;
        }

        return hash('sha256', json_encode([$ingredients, $this->recipeMethod($snapshot)], JSON_THROW_ON_ERROR));
    }

    public function sameRecipe(array $first, array $second): bool
    {
        $identity = $this->recipeIdentity($first);
        if ($identity !== null && $identity === $this->recipeIdentity($second)) {
            return true;
        }

        return ($this->recipeMethod($first) === '' || $this->recipeMethod($second) === '' || $this->recipeMethod($first) === $this->recipeMethod($second))
            && $this->recipeIngredients($first) !== null
            && $this->recipeIngredients($first) === $this->recipeIngredients($second);
    }

    private function recipeIngredients(array $snapshot): ?array
    {
        $keys = [];
        foreach ($snapshot['ingredients'] ?? [] as $row) {
            if (trim($row['name'] ?? '') !== '') {
                $keys[] = mb_strtolower(preg_replace('/\s+/u', ' ', trim($row['name']).'|'.trim($row['preparation_state'] ?? '')));
            } elseif (isset($row['food_key'])) {
                $keys[] = 'food:'.$row['food_key'];
            } elseif (isset($row['ingredient_id'])) {
                $keys[] = 'id:'.$row['ingredient_id'];
            } else {
                return null;
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys === [] ? null : $keys;
    }

    private function recipeMethod(array $snapshot): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($snapshot['instructions'] ?? '')));
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function entryToken(MealPlanEntry $entry): string
    {
        return $this->hash([
            'id' => $entry->id,
            'plan_date' => $entry->plan_date->format('Y-m-d'),
            'slot' => $entry->slot,
            'updated_at' => $entry->getRawOriginal('updated_at'),
            'snapshot' => $entry->snapshot,
        ]);
    }
}
