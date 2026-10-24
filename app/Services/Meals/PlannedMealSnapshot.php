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
            $matches = $ingredients->filter(fn ($ingredient) =>
                $ingredient->name === $row['name']
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

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function entryToken(MealPlanEntry $entry): string
    {
        return $this->hash([
            'id' => $entry->id,
            'updated_at' => $entry->getRawOriginal('updated_at'),
            'snapshot' => $entry->snapshot,
        ]);
    }
}
