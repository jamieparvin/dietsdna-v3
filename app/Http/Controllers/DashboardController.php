<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Services\Meals\MealPlanSlots;
use App\Services\Nutrition\NutritionTargetCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, NutritionTargetCalculator $calculator): View
    {
        $user = $request->user();
        $today = CarbonImmutable::now('Europe/London')->startOfDay();
        $date = $today->toDateString();
        $profile = $user->nutritionProfile()->first();
        $targets = $calculator->calculate($profile);
        $slots = MealPlanSlots::forCount((int) $profile?->meals_per_day, (int) ($profile?->snacks_per_day ?? 0));
        $weekStart = $today->startOfWeek(1);
        // An exclusive next-Monday bound includes all Sunday date/datetime values.
        $weekEntries = MealPlanEntry::query()->where('user_id', $user->id)
            ->where('plan_date', '>=', $weekStart->toDateString())
            ->where('plan_date', '<', $weekStart->addWeek()->toDateString())
            ->orderBy('plan_date')->orderBy('id')->get();
        $entries = $weekEntries->filter(fn ($entry) => $entry->plan_date->format('Y-m-d') === $date)->keyBy('slot');
        $currentFilled = $entries->keys()->intersect(array_keys($slots))->count();
        foreach ($entries as $slot => $entry) {
            if (! isset($slots[$slot])) {
                $slots[$slot] = ucfirst(str_replace('_', ' ', $slot)).' · previous pattern';
            }
        }
        $orderedSlots = collect($slots)->sortBy(fn ($label, $slot) => MealPlanSlots::displayOrder($slot))->all();
        $rows = [];
        $totals = ['energy_kcal' => 0.0, 'protein_g' => 0.0, 'carbohydrate_g' => 0.0,
            'fat_g' => 0.0, 'estimated_available_carbohydrate_g' => 0.0];
        foreach ($orderedSlots as $slot => $label) {
            $entry = $entries->get($slot);
            $snapshot = $entry?->snapshot ?? [];
            $nutrition = [];
            foreach (array_keys($totals) as $field) {
                $nutrition[$field] = $this->number($snapshot['totals'][$field] ?? null);
            }
            if ($nutrition['estimated_available_carbohydrate_g'] === null) {
                $carbs = $nutrition['carbohydrate_g'];
                $fibre = $this->number($snapshot['totals']['fibre_g'] ?? null);
                if ($carbs !== null && $fibre !== null && $fibre <= $carbs) {
                    $nutrition['estimated_available_carbohydrate_g'] = $carbs - $fibre;
                }
            }
            if ($entry) {
                foreach ($totals as $field => $value) {
                    // Missing historical nutrition is unknown, never silently zero.
                    $totals[$field] = $value === null || $nutrition[$field] === null ? null : $value + $nutrition[$field];
                }
            }
            $rows[] = compact('slot', 'label', 'entry', 'snapshot', 'nutrition');
        }
        $definitions = [
            ['key' => 'energy_kcal', 'target' => 'calories_kcal', 'label' => 'Calories', 'unit' => 'kcal', 'decimals' => 0, 'color' => '#19815a'],
            ['key' => 'protein_g', 'target' => 'protein_g', 'label' => 'Protein', 'unit' => 'g', 'decimals' => 1, 'color' => '#6688ae'],
            ['key' => 'estimated_available_carbohydrate_g', 'target' => 'carbohydrate_g', 'label' => 'Available carbs', 'unit' => 'g', 'decimals' => 1, 'color' => '#c49a51'],
            ['key' => 'fat_g', 'target' => 'fat_g', 'label' => 'Fat', 'unit' => 'g', 'decimals' => 1, 'color' => '#a77997'],
        ];
        $metrics = array_map(function ($metric) use ($targets, $totals) {
            $guide = ($targets['status'] ?? null) === 'ready' ? $this->number($targets[$metric['target']] ?? null) : null;
            $planned = $totals[$metric['key']];
            $percent = $planned !== null && $guide !== null && $guide > 0 ? 100 * $planned / $guide : null;
            return $metric + compact('guide', 'planned', 'percent');
        }, $definitions);
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->addDays($i);
            $count = $weekEntries->filter(fn ($entry) => $entry->plan_date->format('Y-m-d') === $day->toDateString())->count();
            $weekDays[] = ['date' => $day, 'count' => $count, 'today' => $day->toDateString() === $date];
        }
        $savedCount = SavedMeal::query()->where('user_id', $user->id)->count();
        $patternCount = count(MealPlanSlots::forCount((int) $profile?->meals_per_day, (int) ($profile?->snacks_per_day ?? 0)));
        $plannedCount = $entries->count();
        $weekPlannedDays = collect($weekDays)->where('count', '>', 0)->count();
        return view('dashboard', compact('today', 'date', 'profile', 'targets', 'rows', 'totals',
            'metrics', 'weekDays', 'weekStart', 'savedCount', 'patternCount', 'currentFilled', 'plannedCount', 'weekPlannedDays'));
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 ? (float) $value : null;
    }
}
