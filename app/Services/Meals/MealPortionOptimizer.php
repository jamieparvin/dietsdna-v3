<?php

namespace App\Services\Meals;

/** Bounded numeric allocation. No API calls, writes, ingredient substitutions or new foods. */
class MealPortionOptimizer
{
    private const DAILY_WEIGHTS = [4.0, 2.0, 1.0, 1.0];
    private const MEAL_WEIGHTS = [0.24, 0.24, 0.03, 0.03];

    public function solve(array $variables, array $base, array $target, array $shares): array
    {
        $mealTargets = [];
        foreach ($shares as $meal => $share) {
            $mealTargets[$meal] = [$share['calories_kcal'], $share['protein_g'], $share['carbohydrate_g'], $share['fat_g']];
        }
        $q = $mass = $actualMeals = [];
        foreach ($variables as $i => $v) {
            $q[$i] = $v['lower'];
            $mass[$v['meal']] = ($mass[$v['meal']] ?? 0) + $q[$i];
            $actualMeals[$v['meal']] ??= [0.0, 0.0, 0.0, 0.0];
        }
        foreach ($variables as $i => $v) {
            $upper = min($v['upper'], $v['mass_limit'] - $mass[$v['meal']] + $q[$i]);
            // An impossible minimum is rejected by the final constraints.
            $next = max($v['lower'], min($upper, $v['original']));
            $mass[$v['meal']] += $next - $q[$i];
            $q[$i] = $next;
        }
        $actual = $base;
        foreach ($variables as $i => $v) {
            for ($k = 0; $k < 4; $k++) {
                $actual[$k] += $v['vector'][$k] * $q[$i];
                $actualMeals[$v['meal']][$k] += $v['vector'][$k] * $q[$i];
            }
        }
        // Convex least squares seed: daily nutrition plus each meal's own share.
        for ($sweep = 0; $sweep < 2000; $sweep++) {
            $largest = 0.0;
            foreach ($variables as $i => $v) {
                $numerator = $denominator = 0.0;
                $mt = $mealTargets[$v['meal']];
                for ($k = 0; $k < 4; $k++) {
                    $a = $v['vector'][$k] / $target[$k];
                    $b = $v['vector'][$k] / $mt[$k];
                    $numerator += self::DAILY_WEIGHTS[$k] * $a * ($actual[$k] / $target[$k] - 1)
                        + self::MEAL_WEIGHTS[$k] * $b * ($actualMeals[$v['meal']][$k] / $mt[$k] - 1);
                    $denominator += self::DAILY_WEIGHTS[$k] * $a * $a + self::MEAL_WEIGHTS[$k] * $b * $b;
                }
                if ($denominator <= 1e-18) {
                    continue;
                }
                $upper = min($v['upper'], $v['mass_limit'] - $mass[$v['meal']] + $q[$i]);
                $next = max($v['lower'], min($upper, $q[$i] - $numerator / $denominator));
                $delta = $next - $q[$i];
                $q[$i] = $next;
                $mass[$v['meal']] += $delta;
                for ($k = 0; $k < 4; $k++) {
                    $actual[$k] += $v['vector'][$k] * $delta;
                    $actualMeals[$v['meal']][$k] += $v['vector'][$k] * $delta;
                }
                $largest = max($largest, abs($delta));
            }
            if ($largest < 0.00001) {
                break;
            }
        }

        // All constraints use nutrition from the catalog. Tighter inner bands
        // leave a margin for gram rounding; final checks use the public bands.
        $rows = [];
        foreach ($target as $k => $value) {
            $tolerance = MealPortionBalancer::TOLERANCES[$k] - 0.01;
            $a = array_map(fn ($v) => $v['vector'][$k] / $value, $variables);
            $this->band($rows, $a, 1 - $tolerance - $base[$k] / $value, 1 + $tolerance - $base[$k] / $value);
        }
        foreach ($shares as $meal => $share) {
            $tolerances = [0 => ($share['is_snack'] ? MealPortionBalancer::SNACK_CALORIE_TOLERANCE : MealPortionBalancer::MAIN_CALORIE_TOLERANCE) - 0.01];
            if (! $share['is_snack']) {
                $tolerances[1] = MealPortionBalancer::MAIN_PROTEIN_TOLERANCE - 0.01;
            }
            foreach ($tolerances as $k => $tolerance) {
                $a = array_map(fn ($v) => $v['meal'] === $meal ? $v['vector'][$k] / $mealTargets[$meal][$k] : 0.0, $variables);
                $this->band($rows, $a, 1 - $tolerance, 1 + $tolerance);
            }
            $limit = $share['is_snack'] ? 500.0 : 1100.0;
            $rows[] = ['a' => array_map(fn ($v) => $v['meal'] === $meal ? 1 / $limit : 0.0, $variables), 'limit' => 1.0];
        }

        // Dykstra projection onto linear bands and the quantity box. Scaling by
        // original portion keeps changes comparable across oil, meat and grains.
        $scale = array_map(fn ($v) => max(10.0, $v['original']), $variables);
        $lower = $upper = $x = $boxCorrection = [];
        foreach ($variables as $i => $v) {
            $lower[$i] = $v['lower'] / $scale[$i];
            $upper[$i] = $v['upper'] / $scale[$i];
            $x[$i] = $q[$i] / $scale[$i];
            $boxCorrection[$i] = 0.0;
        }
        $projectedRows = [];
        foreach ($rows as $row) {
            $minimum = 0.0;
            $a = [];
            foreach ($variables as $i => $v) {
                $minimum += $row['a'][$i] * ($row['a'][$i] >= 0 ? $v['lower'] : $v['upper']);
                $a[$i] = $row['a'][$i] * $scale[$i];
            }
            if ($minimum > $row['limit'] + 1e-8) {
                return $this->rounded($q, $variables, $rows);
            }
            $projectedRows[] = ['a' => $a, 'norm' => $this->dot($a, $a), 'limit' => $row['limit'], 'correction' => 0.0];
        }
        $best = $q;
        $bestViolation = $this->violation($q, $variables, $rows);
        for ($cycle = 0; $cycle < 1600 && $bestViolation > 1e-7; $cycle++) {
            foreach ($projectedRows as &$row) {
                if ($row['norm'] <= 1e-20) {
                    continue;
                }
                foreach ($x as $i => $unused) {
                    $x[$i] += $row['correction'] * $row['a'][$i];
                }
                $correction = max(0.0, ($this->dot($row['a'], $x) - $row['limit']) / $row['norm']);
                foreach ($x as $i => $unused) {
                    $x[$i] -= $correction * $row['a'][$i];
                }
                $row['correction'] = $correction;
            }
            unset($row);
            foreach ($x as $i => $unused) {
                $y = $x[$i] + $boxCorrection[$i];
                $x[$i] = max($lower[$i], min($upper[$i], $y));
                $boxCorrection[$i] = $y - $x[$i];
            }
            if ($cycle % 10 === 0) {
                $trial = array_map(fn ($value, $s) => $value * $s, $x, $scale);
                $violation = $this->violation($trial, $variables, $rows);
                if ($violation < $bestViolation) {
                    $best = $trial;
                    $bestViolation = $violation;
                }
            }
        }
        return $this->rounded($best, $variables, $rows);
    }

    private function band(array &$rows, array $a, float $lower, float $upper): void
    {
        $rows[] = ['a' => $a, 'limit' => $upper];
        $rows[] = ['a' => array_map(fn ($value) => -$value, $a), 'limit' => -$lower];
    }

    private function rounded(array $q, array $variables, array $rows): array
    {
        $result = [];
        foreach ($variables as $i => $v) {
            $result[$i] = round(max($v['lower'], min($v['upper'], round($q[$i] / $v['step']) * $v['step'])), 2);
        }
        // Repair rounding near a meal mass limit without exceeding any box bound.
        for ($pass = 0; $pass < 4; $pass++) {
            $before = $this->violation($result, $variables, $rows);
            foreach ($variables as $i => $v) {
                foreach ([-1, 1] as $direction) {
                    $trial = $result;
                    $trial[$i] = round($trial[$i] + $direction * $v['step'], 2);
                    if ($trial[$i] < $v['lower'] - 1e-9 || $trial[$i] > $v['upper'] + 1e-9) {
                        continue;
                    }
                    if ($this->violation($trial, $variables, $rows) + 1e-12 < $this->violation($result, $variables, $rows)) {
                        $result = $trial;
                    }
                }
            }
            if ($this->violation($result, $variables, $rows) >= $before - 1e-12) {
                break;
            }
        }
        return $result;
    }

    private function violation(array $q, array $variables, array $rows): float
    {
        $maximum = 0.0;
        foreach ($rows as $row) {
            $maximum = max($maximum, $this->dot($row['a'], $q) - $row['limit']);
        }
        foreach ($variables as $i => $v) {
            $maximum = max($maximum, ($v['lower'] - $q[$i]) / max(1, $v['upper']), ($q[$i] - $v['upper']) / max(1, $v['upper']));
        }
        return $maximum;
    }

    private function dot(array $a, array $b): float
    {
        $result = 0.0;
        foreach ($a as $i => $value) {
            $result += $value * $b[$i];
        }
        return $result;
    }
}
