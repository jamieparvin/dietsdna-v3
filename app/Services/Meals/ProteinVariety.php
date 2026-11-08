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
            'family' => match ($category) {
                'fish & seafood' => 'fish',
                'meat & poultry' => 'meat',
                default => 'other',
            },
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

    /**
     * Substantial main sources count fully; secondary protein contributes in proportion to its size.
     * A yoghurt base still counts fully even when it supplies few protein grams.
     */
    public function weights(array $snapshot, array $catalog): array
    {
        $protein = [];
        $total = 0.0;
        $quantities = [];
        foreach ($snapshot['ingredients'] ?? [] as $row) {
            $food = $catalog[$row['ingredient_id'] ?? 0] ?? null;
            if (! $food || ! is_numeric($row['quantity_g'] ?? null) || ! is_numeric($food['protein_g'] ?? null)) {
                continue;
            }
            $grams = max(0, $row['quantity_g'] * $food['protein_g'] / 100);
            $total += $grams;
            $group = $this->food($food);
            if ($group && $grams > 0) {
                $protein[$group['source']] = ($protein[$group['source']] ?? 0) + $grams;
                $quantities[$group['source']] = ($quantities[$group['source']] ?? 0) + max(0, $row['quantity_g']);
            }
        }
        $main = $this->main($snapshot, $catalog);
        foreach ($protein as $source => $grams) {
            // Soft variety weights, not nutrition requirements or portion limits.
            $protein[$source] = $source === $main
                ? min(1.0, max($grams / 15, $quantities[$source] / 150))
                : min(1.0, $grams / 15, $grams / max(0.01, $total) / 0.25);
        }

        return $protein;
    }

    public function usage(array $rows, array $catalog): array
    {
        $usage = [];
        foreach ($rows as $row) {
            foreach ($this->weights($row['snapshot'], $catalog) as $source => $weight) {
                $usage[$source] = ($usage[$source] ?? 0) + $weight;
            }
        }

        return $usage;
    }

    public function score(array $rows, array $catalog, array $usage): float
    {
        $score = 0.0;
        foreach ($rows as $row) {
            foreach ($this->weights($row['snapshot'], $catalog) as $source => $weight) {
                $score += $weight * ($usage[$source] ?? 0);
                $usage[$source] = ($usage[$source] ?? 0) + $weight;
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
            $groups[$key] ??= ['source' => $key, 'ingredient_ids' => [], 'liked' => false, 'uses' => $usage[$key] ?? 0, 'family' => $group['family']];
            $groups[$key]['ingredient_ids'][] = (int) $food['id'];
            $groups[$key]['liked'] = $groups[$key]['liked'] || ($food['liked'] ?? false);
        }
        $families = [];
        foreach ($groups as $group) {
            $families[$group['family']] = ($families[$group['family']] ?? 0) + $group['uses'];
        }
        $reviewedOrder = array_flip(array_keys(config('meal-variety', [])));
        $rank = function ($group) use ($type, &$families, $reviewedOrder) {
            return [
                ! $group['liked'], $group['uses'],
                // Keep familiar breakfast/snack guidance, while offering meat and fish
                // before dairy-based mains when equally liked, unused choices exist.
                in_array($type, ['lunch', 'dinner'], true) && $group['family'] === 'other',
                $families[$group['family']] ?? 0,
                $reviewedOrder[str_replace('reviewed:', '', $group['source'])] ?? PHP_INT_MAX,
                $group['source'],
            ];
        };
        $ordered = [];
        while ($groups) {
            // Re-evaluate family usage after each assignment so a three-option
            // request includes both meat and fish instead of alphabetical neighbours.
            usort($groups, fn ($a, $b) => $rank($a) <=> $rank($b));
            $next = array_shift($groups);
            $ordered[] = $next;
            $families[$next['family']] = ($families[$next['family']] ?? 0) + 1;
        }

        return $ordered;
    }
}
