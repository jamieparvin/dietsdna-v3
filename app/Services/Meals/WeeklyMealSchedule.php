<?php

namespace App\Services\Meals;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

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
                if (count($pool) < $occurrences[$type]) {
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
    public function buildBalanced(string $startDate, array $slots, array $pools, array $fixed, array $catalog, array $targets, MealPortionBalancer $balancer): array
    {
        $initial = $this->build($startDate, $slots, $pools,
            array_map(fn ($row) => $row['date'].'|'.$row['slot'], $fixed));
        $days = [];
        foreach ($initial as $row) {
            $days[$row['date']][] = $row;
        }
        $result = $cache = [];
        foreach ($days as $date => $day) {
            $dayFixed = array_values(array_filter($fixed, fn ($row) => $row['date'] === $date));
            $attempts = 0;
            $last = null;
            $balanced = null;
            foreach ($this->candidates($day, $pools) as $candidate) {
                if (++$attempts > 24) {
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
        }
        $balancer->assertFits($result, $fixed, $targets);
        return $result;
    }

    private function candidates(array $day, array $pools): array
    {
        $initial = [];
        $options = [];
        foreach ($day as $i => $row) {
            $options[$i] = array_values($pools[$row['meal_type']] ?? []);
            $found = array_search($row['snapshot'], $options[$i], true);
            $initial[$i] = $found === false ? 0 : $found;
        }
        $combinations = [];
        $visit = function (int $i, array $choices, array $used) use (&$visit, &$combinations, $day, $options, $initial): void {
            if ($i === count($day)) {
                $changes = $distance = 0;
                foreach ($choices as $index => $choice) {
                    $changes += $choice !== $initial[$index] ? 1 : 0;
                    $distance += ($choice - $initial[$index] + count($options[$index])) % count($options[$index]);
                }
                $combinations[] = ['choices' => $choices, 'changes' => $changes, 'distance' => $distance];
                return;
            }
            $type = $day[$i]['meal_type'];
            foreach ($options[$i] as $choice => $snapshot) {
                // The same food option cannot occupy two slots of one type today.
                $identity = hash('sha256', serialize($snapshot));
                if (isset($used[$type][$identity])) {
                    continue;
                }
                $next = $used;
                $next[$type][$identity] = true;
                $visit($i + 1, [...$choices, $choice], $next);
            }
        };
        $visit(0, [], []);
        usort($combinations, fn ($a, $b) => [$a['changes'], $a['distance'], $a['choices']] <=> [$b['changes'], $b['distance'], $b['choices']]);
        $candidates = [];
        foreach (array_slice($combinations, 0, 24) as $combination) {
            $candidate = $day;
            foreach ($combination['choices'] as $i => $choice) {
                $candidate[$i]['snapshot'] = $options[$i][$choice];
            }
            $candidates[] = $candidate;
        }
        return $candidates;
    }
}
