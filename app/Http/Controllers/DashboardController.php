<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealPlanSlots;
use App\Services\Nutrition\BodyMassIndex;
use App\Services\Nutrition\SnapshotNutrition;
use App\Services\Onboarding;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Onboarding $onboarding): View
    {
        $user = $request->user();
        $setup = $onboarding->state($user);
        if (! $setup['complete'] || ! MealPlanEntry::where('user_id', $user->id)->exists()) {
            $preview = $setup['complete'] ? WeeklyMealDraft::where('user_id', $user->id)->whereIn('status', ['queued', 'generating', 'ready'])->latest('id')->first() : null;

            return view('onboarding', compact('setup', 'preview'));
        }
        $today = CarbonImmutable::now('Europe/London')->startOfDay();
        $date = $today->toDateString();
        $profile = $user->nutritionProfile()->first();
        $targets = $setup['targets'];
        $bmi = app(BodyMassIndex::class)->forProgress($user);
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
        $totals = SnapshotNutrition::aggregate($entries->pluck('snapshot'));
        foreach ($orderedSlots as $slot => $label) {
            $entry = $entries->get($slot);
            $snapshot = $entry?->snapshot ?? [];
            $nutrition = SnapshotNutrition::totals($snapshot);
            $rows[] = compact('slot', 'label', 'entry', 'snapshot', 'nutrition');
        }
        $definitions = [
            ['key' => 'energy_kcal', 'target' => 'calories_kcal', 'label' => 'Calories', 'unit' => 'kcal', 'decimals' => 0, 'color' => '#19815a'],
            ['key' => 'protein_g', 'target' => 'protein_g', 'label' => 'Protein', 'unit' => 'g', 'decimals' => 1, 'color' => '#6688ae'],
            ['key' => 'estimated_available_carbohydrate_g', 'target' => 'carbohydrate_g', 'label' => 'Available carbs', 'unit' => 'g', 'decimals' => 1, 'color' => '#c49a51'],
            ['key' => 'fat_g', 'target' => 'fat_g', 'label' => 'Fat', 'unit' => 'g', 'decimals' => 1, 'color' => '#a77997'],
        ];
        $metrics = array_map(function ($metric) use ($targets, $totals) {
            $guide = $metric['target'] !== null && ($targets['status'] ?? null) === 'ready' ? SnapshotNutrition::number($targets[$metric['target']] ?? null) : null;
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
            'metrics', 'weekDays', 'weekStart', 'savedCount', 'patternCount', 'currentFilled', 'plannedCount', 'weekPlannedDays', 'setup', 'bmi'));
    }
}
