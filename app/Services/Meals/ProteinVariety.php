<?php

namespace App\Services\Meals;

/** Soft variety guidance only. Never decides eligibility or edits preference groups. */
class ProteinVariety
{
    public function food(array $food): ?array
    {
        $category = mb_strtolower(trim($food['category'] ?? ''));
        if (! in_array($category, ['fish & seafood', 'meat & poultry', 'protein & dairy'], true)) {
            return null;
        }
        $source = null;
        foreach (config('meal-variety', []) as $key => $codes) {
            if (in_array($food['food_code'] ?? '', $codes, true)) {
                $source = 'reviewed:'.$key;
                break;
            }
        }

        return [
            'source' => $source ?? 'group:'.($food['preference_group_key'] ?? $food['preference_key'] ?? $food['id']),
            'reviewed' => $source !== null,
            'meal_types' => in_array($category, ['fish & seafood', 'meat & poultry'], true)
                ? ['lunch', 'dinner'] : ['breakfast', 'lunch', 'dinner', 'snack'],
        ];
    }

    /** Choose the group supplying most protein grams, summing linked preparations. */
    public function main(array $snapshot, array $catalog): ?string
    {
        $protein = [];
        foreach ($snapshot['ingredients'] ?? [] as $row) {
            $food = $catalog[$row['ingredient_id'] ?? 0] ?? null;
            $group = $food ? $this->food($food) : null;
            if ($group && is_numeric($row['quantity_g'] ?? null) && is_numeric($food['protein_g'] ?? null)) {
                $protein[$group['source']] = ($protein[$group['source']] ?? 0) + $row['quantity_g'] * $food['protein_g'] / 100;
            }
        }
        arsort($protein);

        return $protein && reset($protein) > 0 ? array_key_first($protein) : null;
    }

    public function usage(array $rows, array $catalog): array
    {
        $usage = [];
        foreach ($rows as $row) {
            $source = $this->main($row['snapshot'], $catalog);
            if ($source !== null) {
                $usage[$source] = ($usage[$source] ?? 0) + 1;
            }
        }

        return $usage;
    }

    public function score(array $rows, array $catalog, array $usage): int
    {
        $score = 0;
        foreach ($rows as $row) {
            $source = $this->main($row['snapshot'], $catalog);
            if ($source !== null) {
                $score += $usage[$source] ?? 0;
                $usage[$source] = ($usage[$source] ?? 0) + 1;
            }
        }

        return $score;
    }

    public function guidance(array $catalog, string $type, array $usage): array
    {
        $groups = [];
        foreach ($catalog as $food) {
            $group = $this->food($food);
            if (! $group || ! in_array($type, $group['meal_types'], true)) {
                continue;
            }
            $key = $group['source'];
            $groups[$key] ??= ['source' => $key, 'ingredient_ids' => [], 'liked' => false, 'uses' => $usage[$key] ?? 0];
            $groups[$key]['ingredient_ids'][] = (int) $food['id'];
            $groups[$key]['liked'] = $groups[$key]['liked'] || ($food['liked'] ?? false);
        }
        usort($groups, fn ($a, $b) => [! $a['liked'], $a['uses'], $a['source']] <=> [! $b['liked'], $b['uses'], $b['source']]);

        return array_values($groups);
    }
}
