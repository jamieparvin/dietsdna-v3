<?php

namespace App\Services\Meals;

use App\Models\MealPlanEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class MealPlanWeekState
{
    /** Includes every Sunday entry whether stored as DATE or with a time. */
    public function query(int $userId, CarbonImmutable $start): Builder
    {
        return MealPlanEntry::query()->where('user_id', $userId)
            ->where('plan_date', '>=', $start->toDateString())
            ->where('plan_date', '<', $start->addDays(7)->toDateString());
    }

    public function capture(int $userId, CarbonImmutable $start): array
    {
        $entries = $this->query($userId, $start)->orderBy('id')->get();

        $fixed = $entries->map(fn ($entry) => [
            'date' => $entry->plan_date->format('Y-m-d'),
            'slot' => $entry->slot,
            'snapshot' => $entry->snapshot,
        ])->all();

        // Keep the existing fingerprint format so normal fill previews remain valid.
        $fingerprint = hash('sha256', json_encode($entries->map(fn ($entry) => [
            'id' => $entry->id, 'slot' => $entry->slot,
            'date' => $entry->plan_date->format('Y-m-d'),
            'saved_meal_id' => $entry->saved_meal_id,
            'snapshot' => $entry->snapshot,
        ])->all(), JSON_THROW_ON_ERROR));

        return compact('entries', 'fixed', 'fingerprint');
    }
}
