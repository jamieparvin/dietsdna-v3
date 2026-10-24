<?php

namespace App\Services\Meals;

use Illuminate\Validation\ValidationException;

/** Numeric allocation against existing targets, not a nutrition prescription. */
class MealPortionBalancer
{
    public const VERSION = 'portion-balance-v1';
    public const TOLERANCES = [0.05, 0.10, 0.10, 0.10];
    private const NUTRIENTS = ['energy_kcal', 'protein_g', 'carbohydrate_g', 'sugars_g', 'fat_g', 'saturates_g', 'fibre_g', 'salt_g'];
    private const WEIGHTS = [4.0, 2.0, 1.0, 1.0];

    /**
     * $catalog is keyed by ingredient ID, with numeric nutrition PER 100 G.
     * $fixed rows have date, slot and snapshot. They are never changed.
     * Only dates containing new proposals are balanced.
     */
    public function balance(array $proposals, array $fixed, array $catalog, array $targets): array
    {
        $target = $this->targetVector($targets);
        $byDay = [];
        foreach ($proposals as $index => $proposal) {
            $byDay[$proposal['date']][] = $index;
        }

        foreach ($byDay as $date => $indices) {
            $base = array_fill(0, 4, 0.0);
            foreach ($fixed as $entry) {
                if ($entry['date'] === $date) {
                    $base = $this->add($base, $this->vector($entry['snapshot']['totals']));
                }
            }
            $variables = [];
            foreach ($indices as $index) {
                $snapshot = $proposals[$index]['snapshot'];
                // Food weights belong to the authoritative, adjustable ingredient list.
                // Keep food names, preparation states, cooking times and temperatures.
                $snapshot['instructions'] = $this->normaliseGramAmounts($snapshot['instructions']);
                $proposals[$index]['snapshot']['instructions'] = $snapshot['instructions'];
                if ($this->hasIngredientAmounts($snapshot['instructions'])) {
                    $this->fail("{$date}: a method contains an unsupported food count or measure. Review its instructions before adjusting portions.");
                }
                foreach ($snapshot['ingredients'] as $rowIndex => $row) {
                    $food = $catalog[$row['ingredient_id']] ?? null;
                    if (! $food) {
                        $this->fail('An ingredient needed for portion balancing is unavailable.');
                    }
                    foreach (self::NUTRIENTS as $field) {
                        if (! isset($food[$field]) || ! is_numeric($food[$field]) || ! is_finite((float) $food[$field]) || $food[$field] < 0) {
                            $this->fail('Complete valid per-100 g nutrition before balancing meals.');
                        }
                    }
                    if ($food['fibre_g'] > $food['carbohydrate_g'] + 0.01) {
                        $this->fail('An ingredient has more fibre than total carbohydrate. Review its carbohydrate basis before balancing.');
                    }
                    $original = (float) $row['quantity_g'];
                    if (! is_finite($original) || $original <= 0) {
                        $this->fail('An ingredient has an invalid quantity.');
                    }
                    $upper = min(1000.0, $original * 3, $this->portionCap($food));
                    $lower = min($upper, max(0.01, $original * 0.25));
                    $step = $upper >= 5 ? 1.0 : 0.01;
                    $lower = ceil(($lower - 1e-9) / $step) * $step;
                    $upper = floor(($upper + 1e-9) / $step) * $step;
                    if ($lower > $upper || $upper <= 0) {
                        $this->fail('A generated ingredient quantity is too small to balance reliably.');
                    }
                    $variables[] = [
                        'meal' => $index, 'row' => $rowIndex,
                        'food' => $food, 'original' => $original,
                        'lower' => $lower, 'upper' => $upper, 'step' => $step,
                        'mass_limit' => $proposals[$index]['meal_type'] === 'snack' ? 500.0 : 1100.0,
                        'vector' => array_map(fn ($value) => $value / 100, $this->vector($food)),
                    ];
                }
            }
            if (! $variables) {
                $this->fail("{$date}: there are no ingredients to adjust.");
            }
            $best = null;
            $bestScore = INF;
            // Different starting points reduce avoidable failures near mass bounds.
            $originalEnergy = 0.0;
            foreach ($variables as $v) {
                $originalEnergy += $v['vector'][0] * $v['original'];
            }
            $scale = max(0.25, min(3.0, ($target[0] - $base[0]) / max(1, $originalEnergy)));
            foreach ([1.0, $scale, 0.25] as $start) {
                $quantities = $this->solve($variables, $base, $target, $start);
                $actual = $base;
                foreach ($variables as $i => $v) {
                    $actual = $this->add($actual, $v['vector'], $quantities[$i]);
                }
                $score = $this->score($actual, $target);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = $quantities;
                }
                if ($this->within($actual, $target)) {
                    $best = $quantities;
                    break;
                }
            }
            foreach ($variables as $i => $v) {
                $proposals[$v['meal']]['snapshot']['ingredients'][$v['row']]['quantity_g'] = $best[$i];
            }
            foreach ($indices as $index) {
                $totals = array_fill_keys(self::NUTRIENTS, 0.0);
                foreach ($proposals[$index]['snapshot']['ingredients'] as $row) {
                    $food = $catalog[$row['ingredient_id']];
                    foreach (self::NUTRIENTS as $field) {
                        $totals[$field] += (float) $food[$field] * $row['quantity_g'] / 100;
                    }
                }
                $totals['estimated_available_carbohydrate_g'] = max(0, $totals['carbohydrate_g'] - $totals['fibre_g']);
                $proposals[$index]['snapshot']['totals'] = $totals;
                unset($proposals[$index]['snapshot']['calorie_difference']);
                $proposals[$index]['snapshot']['portion_balance'] = [
                    'version' => self::VERSION,
                    'carbohydrate_basis' => 'estimated_available_total_minus_fibre',
                    'daily_targets' => $targets,
                ];
            }
        }
        // Recheck ROUNDING and fixed meals, not just the optimizer's continuous values.
        $this->assertFits($proposals, $fixed, $targets);
        return $proposals;
    }

    /** Revalidation before confirmation; this never resizes a saved meal. */
    public function assertFits(array $proposals, array $fixed, array $targets): void
    {
        $target = $this->targetVector($targets);
        $days = [];
        foreach ($proposals as $proposal) {
            $days[$proposal['date']] ??= array_fill(0, 4, 0.0);
            $days[$proposal['date']] = $this->add($days[$proposal['date']], $this->vector($proposal['snapshot']['totals']));
        }
        foreach ($fixed as $entry) {
            if (isset($days[$entry['date']])) {
                $days[$entry['date']] = $this->add($days[$entry['date']], $this->vector($entry['snapshot']['totals']));
            }
        }
        foreach ($days as $date => $actual) {
            if (! $this->within($actual, $target)) {
                $this->fail($date.': these meal combinations could not meet all targets within the portion limits. '
                    .'Calculated '.number_format($actual[0]).' / '.number_format($target[0]).' kcal, '
                    .number_format($actual[1], 1).' / '.number_format($target[1], 1).' g protein, '
                    .number_format($actual[2], 1).' / '.number_format($target[2], 1).' g estimated available carbs, '
                    .number_format($actual[3], 1).' / '.number_format($target[3], 1).' g fat. '
                    .'No new meals were saved. Try different meal options; existing planned meals remain unchanged.');
            }
        }
    }

    private function solve(array $variables, array $base, array $target, float $start): array
    {
        $q = [];
        $mass = [];
        foreach ($variables as $i => $v) {
            $q[$i] = $v['lower'];
            $mass[$v['meal']] = ($mass[$v['meal']] ?? 0) + $q[$i];
        }
        foreach ($variables as $i => $v) {
            if ($mass[$v['meal']] > $v['mass_limit'] + 1e-6) {
                $this->fail('The minimum portions exceed the meal weight limit. Generate different options.');
            }
        }
        foreach ($variables as $i => $v) {
            $upper = min($v['upper'], $v['mass_limit'] - $mass[$v['meal']] + $q[$i]);
            $next = max($v['lower'], min($upper, $v['original'] * $start));
            $mass[$v['meal']] += $next - $q[$i];
            $q[$i] = $next;
        }
        $actual = $base;
        foreach ($variables as $i => $v) {
            $actual = $this->add($actual, $v['vector'], $q[$i]);
        }
        for ($sweep = 0; $sweep < 3000; $sweep++) {
            $largest = 0.0;
            foreach ($variables as $i => $v) {
                $numerator = 0.0;
                $denominator = 0.0;
                for ($k = 0; $k < 4; $k++) {
                    $coefficient = $v['vector'][$k] / $target[$k];
                    $numerator += self::WEIGHTS[$k] * $coefficient * ($actual[$k] / $target[$k] - 1);
                    $denominator += self::WEIGHTS[$k] * $coefficient ** 2;
                }
                if ($denominator <= 1e-18) {
                    continue;
                }
                $upper = min($v['upper'], $v['mass_limit'] - $mass[$v['meal']] + $q[$i]);
                $next = max($v['lower'], min($upper, $q[$i] - $numerator / $denominator));
                $delta = $next - $q[$i];
                $q[$i] = $next;
                $mass[$v['meal']] += $delta;
                $actual = $this->add($actual, $v['vector'], $delta);
                $largest = max($largest, abs($delta));
            }
            if ($largest < 0.00001) {
                break;
            }
        }
        // Floor first, then try one rounding step if it improves the objective.
        foreach ($variables as $i => $v) {
            $next = max($v['lower'], floor(($q[$i] + 1e-9) / $v['step']) * $v['step']);
            $delta = $next - $q[$i];
            $q[$i] = round($next, 2);
            $mass[$v['meal']] += $delta;
            $actual = $this->add($actual, $v['vector'], $delta);
        }
        foreach ($variables as $i => $v) {
            if ($q[$i] + $v['step'] <= $v['upper'] + 1e-9 && $mass[$v['meal']] + $v['step'] <= $v['mass_limit'] + 1e-9) {
                $trial = $this->add($actual, $v['vector'], $v['step']);
                if ($this->score($trial, $target) < $this->score($actual, $target)) {
                    $q[$i] = round($q[$i] + $v['step'], 2);
                    $mass[$v['meal']] += $v['step'];
                    $actual = $trial;
                }
            }
        }
        return $q;
    }

    private function targetVector(array $targets): array
    {
        $values = [];
        foreach (['calories_kcal', 'protein_g', 'carbohydrate_g', 'fat_g'] as $field) {
            $value = $targets[$field] ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value) || $value <= 0) {
                $this->fail('Valid daily calorie and macro targets are required.');
            }
            $values[] = (float) $value;
        }
        return $values;
    }

    private function vector(array $nutrition): array
    {
        foreach (['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g', 'fibre_g'] as $field) {
            if (! isset($nutrition[$field]) || ! is_numeric($nutrition[$field]) || ! is_finite((float) $nutrition[$field]) || $nutrition[$field] < 0) {
                $this->fail('A meal is missing valid nutrition data needed for balancing.');
            }
        }
        if ($nutrition['fibre_g'] > $nutrition['carbohydrate_g'] + 0.01) {
            $this->fail('Review a meal whose fibre exceeds total carbohydrate.');
        }
        return [(float) $nutrition['energy_kcal'], (float) $nutrition['protein_g'],
            max(0, (float) $nutrition['carbohydrate_g'] - (float) $nutrition['fibre_g']), (float) $nutrition['fat_g']];
    }

    private function portionCap(array $food): float
    {
        // Product caps, not clinician-validated serving prescriptions.
        if ($food['fat_g'] >= 80 && $food['protein_g'] < 5) {
            return 25.0; // Oils and similar concentrated fats.
        }
        if ($food['energy_kcal'] >= 450 && $food['fat_g'] >= 25) {
            return 60.0; // Nuts, seeds and similar energy-dense foods.
        }
        if ($food['carbohydrate_g'] >= 50 && $food['fat_g'] < 20) {
            return 150.0; // Dry grains, bread and similar foods.
        }
        return 400.0;
    }

    private function normaliseGramAmounts(string $method): string
    {
        // Only simple, absolute mass references. Ratios, ranges, fractions and
        // per-weight instructions remain blocked for review, rather than guessed.
        $number = '\d+(?:[.,]\d+)?';
        $unit = '(?:kilograms?|grams?|kg|g)\b';
        $complex = '/(?:\b'.$number.'\s*[-–\/]\s*'.$number.'\s*'.$unit
            .'|\b'.$number.'\s*'.$unit.'\s*(?:\/|per\b|[-–])'
            .'|\bper\s+'.$number.'\s*'.$unit
            .'|\b\d+(?:[.,]\d+){2,}\s*'.$unit.')/iu';
        if (preg_match($complex, $method)) {
            return $method;
        }
        return preg_replace(
            '/(?<![-–\/\d.,])\b\d+(?:[.,]\d+)?\s*(?:kilograms?|grams?|kg|g)\b\s*(?:of\s+)?/iu',
            'the listed amount of ',
            $method
        ) ?? $method;
    }

    private function hasIngredientAmounts(string $method): bool
    {
        return (bool) preg_match('/\b(?:\d+(?:[.,]\d+)?|one|two|three|four|five|six|half|quarter)\s*(?:g\b|kg\b|ml\b|grams?\b|kilograms?\b|millilitres?\b|eggs?\b|cups?\b|tablespoons?\b|teaspoons?\b|tbsp\b|tsp\b|slices?\b|pieces?\b|handfuls?\b)/iu', $method);
    }

    private function add(array $left, array $right, float $scale = 1): array
    {
        for ($k = 0; $k < 4; $k++) {
            $left[$k] += $right[$k] * $scale;
        }
        return $left;
    }

    private function score(array $actual, array $target): float
    {
        $score = 0.0;
        for ($k = 0; $k < 4; $k++) {
            $score += self::WEIGHTS[$k] * ($actual[$k] / $target[$k] - 1) ** 2;
        }
        return $score;
    }

    private function within(array $actual, array $target): bool
    {
        for ($k = 0; $k < 4; $k++) {
            if (abs($actual[$k] - $target[$k]) / $target[$k] > self::TOLERANCES[$k] + 1e-9) {
                return false;
            }
        }
        return true;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['week' => $message]);
    }
}
