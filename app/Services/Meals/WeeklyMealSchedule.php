<?php

namespace App\Services\Meals;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WeeklyMealSchedule
{
    public static function typeForSlot(string $slot): string
    {
        return MealPlanSlots::typeForSlot($slot);
    }

    public function build(string $startDate, array $slots, array $pools, array $occupied): array
    {
        $start = CarbonImmutable::parse($startDate, 'Europe/London');
        $blocked = array_fill_keys($occupied, true);
        $proposals = [];
        for ($day = 0; $day < 7; $day++) {
            $date = $start->addDays($day)->toDateString();
            $occurrences = [];
            foreach ($slots as $slot => $label) {
                $type = self::typeForSlot($slot);
                $offset = $occurrences[$type] ?? 0;
                $occurrences[$type] = $offset + 1;
                if (isset($blocked[$date.'|'.$slot])) {
                    continue;
                }
                $pool = array_values($pools[$type] ?? []);
                if (! count($pool)) {
                    throw new InvalidArgumentException('Not enough meal options for '.$type.'.');
                }
                $proposals[] = [
                    'date' => $date,
                    'slot' => $slot,
                    'meal_type' => $type,
                    'snapshot' => $pool[($day + $offset) % count($pool)],
                ];
            }
        }

        return $proposals;
    }

    /** Try stored options only: never calls AI or changes an existing planned meal. */
    public function buildBalanced(string $startDate, array $slots, array $pools, array $fixed, array $catalog, array $targets, MealPortionBalancer $balancer, ?array $proteinUsage = null, ?int $preferredMinutes = null): array
    {
        $initial = $this->build($startDate, $slots, $pools,
            array_map(fn ($row) => $row['date'].'|'.$row['slot'], $fixed));
        $days = [];
        foreach ($initial as $row) {
            $days[$row['date']][] = $row;
        }
        $result = $cache = [];
        $variety = app(ProteinVariety::class);
        $usage = $proteinUsage ?? $variety->usage($fixed, $catalog);
        $breakfastUsage = $this->breakfastUsage($fixed);
        foreach ($days as $date => $day) {
            $dayFixed = array_values(array_filter($fixed, fn ($row) => $row['date'] === $date));
            $attempts = 0;
            $last = null;
            $balanced = null;
            $dayUsage = $variety->usage($dayFixed, $catalog);
            $rank = fn ($rows) => [
                $variety->score($rows, $catalog, $dayUsage),
                $variety->score($rows, $catalog, $usage),
                $this->breakfastScore($rows, $breakfastUsage),
                MealPreparationTime::scoreRows($rows, $preferredMinutes),
            ];
            // Keep 24 original numeric fallbacks plus 24 variety/time choices.
            // Rank before trimming so suitable less-used sources can reach balancing.
            $candidates = $this->candidates($day, $pools, $rank);
            usort($candidates, fn ($a, $b) => $rank($a) <=> $rank($b));
            foreach ($candidates as $candidate) {
                if (++$attempts > 48) {
                    break;
                }
                // Rotating patterns often recur later in the week. Cache successful
                // numeric results only, excluding the date but including fixed food.
                $key = hash('sha256', serialize([
                    array_map(fn ($r) => [$r['slot'], $r['snapshot']], $candidate),
                    array_map(fn ($r) => [$r['slot'], $r['snapshot']], $dayFixed),
                    $targets,
                ]));
                if (isset($cache[$key])) {
                    $balanced = $cache[$key];
                    foreach ($balanced as &$row) {
                        $row['date'] = $date;
                    }
                    unset($row);
                    break;
                }
                try {
                    $balanced = $balancer->balance($candidate, $dayFixed, $catalog, $targets);
                    $cache[$key] = $balanced;
                    break;
                } catch (ValidationException $exception) {
                    $last = $exception;
                }
            }
            if ($balanced === null) {
                throw $last ?? ValidationException::withMessages(['week' => $date.': no suitable stored meal combination was available.']);
            }
            array_push($result, ...$balanced);
            foreach ($this->breakfastUsage($balanced) as $identity => $count) {
                $breakfastUsage[$identity] = ($breakfastUsage[$identity] ?? 0) + $count;
            }
            foreach ($variety->usage($balanced, $catalog) as $key => $count) {
                $usage[$key] = ($usage[$key] ?? 0) + $count;
            }
        }
        $balancer->assertFits($result, $fixed, $targets);

        return $result;
    }

    private function breakfastUsage(array $rows): array
    {
        $usage = [];
        $snapshots = app(PlannedMealSnapshot::class);
        foreach ($rows as $row) {
            if (self::typeForSlot($row['slot']) !== 'breakfast') {
                continue;
            }
            $identity = $snapshots->recipeIdentity($row['snapshot']);
            if ($identity !== null) {
                $usage[$identity] = ($usage[$identity] ?? 0) + 1;
            }
        }

        return $usage;
    }

    private function breakfastScore(array $rows, array $usage): int
    {
        $score = 0;
        foreach ($this->breakfastUsage($rows) as $identity => $count) {
            $score += $count * ($usage[$identity] ?? 0);
        }

        return $score;
    }

    private function candidates(array $day, array $pools, callable $rank): array
    {
        $initial = [];
        $options = [];
        foreach ($day as $i => $row) {
            $options[$i] = array_values($pools[$row['meal_type']] ?? []);
            $found = array_search($row['snapshot'], $options[$i], true);
            $initial[$i] = $found === false ? 0 : $found;
        }
        $combinations = [];
        $required = array_count_values(array_column($day, 'meal_type'));
        $visit = function (int $i, array $choices, array $used) use (&$visit, &$combinations, $day, $options, $initial, $required, $rank): void {
            if ($i === count($day)) {
                $changes = $distance = 0;
                foreach ($choices as $index => $choice) {
                    $changes += $choice !== $initial[$index] ? 1 : 0;
                    $distance += ($choice - $initial[$index] + count($options[$index])) % count($options[$index]);
                }
                $rankingRows = [];
                foreach ($choices as $index => $choice) {
                    $rankingRows[] = array_replace($day[$index], ['snapshot' => $options[$index][$choice]]);
                }
                $combinations[] = ['choices' => $choices, 'changes' => $changes, 'distance' => $distance, 'rank' => $rank($rankingRows)];

                return;
            }
            $type = $day[$i]['meal_type'];
            foreach ($options[$i] as $choice => $snapshot) {
                // The same food option cannot occupy two slots of one type today.
                $identity = hash('sha256', serialize($snapshot));
                $distinct = count(array_unique(array_map(fn ($option) => hash('sha256', serialize($option)), $options[$i])));
                if ($distinct >= $required[$type] && isset($used[$type][$identity])) {
                    continue;
                }
                $next = $used;
                $next[$type][$identity] = true;
                $visit($i + 1, [...$choices, $choice], $next);
            }
        };
        $visit(0, [], []);
        usort($combinations, fn ($a, $b) => [$a['changes'], $a['distance'], $a['choices']] <=> [$b['changes'], $b['distance'], $b['choices']]);
        $selected = [];
        foreach (array_slice($combinations, 0, 24) as $combination) {
            $selected[implode(',', $combination['choices'])] = $combination;
        }
        usort($combinations, fn ($a, $b) => [$a['rank'], $a['changes'], $a['distance'], $a['choices']] <=> [$b['rank'], $b['changes'], $b['distance'], $b['choices']]);
        foreach (array_slice($combinations, 0, 24) as $combination) {
            $selected[implode(',', $combination['choices'])] = $combination;
        }
        $candidates = [];
        foreach ($selected as $combination) {
            $candidate = $day;
            foreach ($combination['choices'] as $i => $choice) {
                $candidate[$i]['snapshot'] = $options[$i][$choice];
            }
            $candidates[] = $candidate;
        }

        return $candidates;
    }
}
