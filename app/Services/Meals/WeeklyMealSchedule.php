<?php

namespace App\Services\Meals;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class WeeklyMealSchedule
{
    public static function typeForSlot(string $slot): string
    {
        return str_starts_with($slot, 'snack_') ? 'snack' : $slot;
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
}
