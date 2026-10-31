<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealFoodExclusions;
use App\Services\Meals\MealPlanSlots;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\SnapshotNutrition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealPlanController extends Controller
{
    public function index(
        Request $request,
        NutritionTargetCalculator $calculator,
        MealFoodExclusions $exclusions
    ) {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = \Carbon\CarbonImmutable::now('Europe/London')->startOfDay();

        $date = $validated['date'] ?? $today->toDateString();

        $selectedDate = \Carbon\CarbonImmutable::parse(
            $date,
            'Europe/London'
        )->startOfDay();

        $weekStart = $selectedDate->startOfWeek(1);
        $weekEnd = $weekStart->addDays(6);

        $profile = $request->user()->nutritionProfile()->first();

        $slots = MealPlanSlots::forCount(
            (int) $profile?->meals_per_day,
            (int) ($profile?->snacks_per_day ?? 0)
        );

        $weekEntries = MealPlanEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('plan_date', [
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
            ])
            ->get();

        $entriesByDate = $weekEntries->groupBy(
            fn ($entry) => $entry->plan_date->format('Y-m-d')
        );

        $entries = $entriesByDate
            ->get($date, collect())
            ->keyBy('slot');

        foreach ($entries as $slot => $entry) {
            if (! isset($slots[$slot])) {
                $slots[$slot] = ucfirst(str_replace('_', ' ', $slot))
                    .' — previous pattern';
            }
        }

        $totals = SnapshotNutrition::aggregate($entries->pluck('snapshot'));
        $identities = $exclusions->identities($request->user());
        $excludedFoods = $entries->mapWithKeys(fn ($entry) => [
            $entry->id => $exclusions->names($entry->snapshot, $identities),
        ])->all();

        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->addDays($offset);

            $dayEntries = $entriesByDate->get(
                $day->toDateString(),
                collect()
            );

            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->format('D'),
                'number' => $day->format('j'),
                'is_today' => $day->isSameDay($today),
                'is_selected' => $day->isSameDay($selectedDate),
                'meal_count' => $dayEntries->count(),
                'calories' => SnapshotNutrition::aggregate($dayEntries->pluck('snapshot'))['energy_kcal'],
            ];
        }

        $previousWeekDate = $selectedDate->subWeek()->toDateString();
        $nextWeekDate = $selectedDate->addWeek()->toDateString();

        $targets = $calculator->calculate($profile);

        return view('meal-plan.index', compact(
            'date',
            'today',
            'selectedDate',
            'weekStart',
            'weekEnd',
            'previousWeekDate',
            'nextWeekDate',
            'days',
            'slots',
            'entries',
            'excludedFoods',
            'totals',
            'targets'
        ));
    }

    public function store(
        Request $request,
        MealIdeaGenerator $generator
    ) {
        $data = $request->validate([
            'saved_meal_id' => ['required', 'integer'],
            'plan_date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['required', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($request, $generator, $data) {
            $user = $request->user();

            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            $meal = SavedMeal::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['saved_meal_id']);

            $profile = $user->nutritionProfile()->first();

            $slots = MealPlanSlots::forCount(
                (int) $profile?->meals_per_day,
                (int) ($profile?->snacks_per_day ?? 0)
            );

            if (! isset($slots[$data['slot']])) {
                $this->fail(
                    'Choose a slot from your current daily meal pattern.'
                );
            }

            // Check against current exclusions and ingredient availability.
            $context = $generator->context($user, $meal->meal_type);
            $eligible = $context['ingredients']->keyBy('id');

            foreach ($meal->snapshot['ingredients'] as $row) {
                $ingredient = $eligible->get($row['ingredient_id'] ?? 0);

                if (
                    ! $ingredient
                    || MealIdeaGenerator::foodKey($ingredient)
                        !== ($row['food_key'] ?? null)
                ) {
                    $this->fail(
                        'This saved meal contains an ingredient that is no '
                        .'longer eligible under your current preferences '
                        .'or ingredient library.'
                    );
                }
            }

            $existing = MealPlanEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('plan_date', $data['plan_date'])
                ->where('slot', $data['slot'])
                ->first();

            if ($existing) {
                if ((int) $existing->saved_meal_id === (int) $meal->id) {
                    return;
                }

                $this->fail(
                    'That slot already contains a meal. Remove it from '
                    .'the plan before adding a replacement.'
                );
            }

            MealPlanEntry::query()->create([
                'user_id' => $user->id,
                'saved_meal_id' => $meal->id,
                'plan_date' => $data['plan_date'],
                'slot' => $data['slot'],
                'snapshot' => $meal->snapshot,
            ]);
        });

        return redirect()
            ->route('meal-plan.index', ['date' => $data['plan_date']])
            ->with('status', 'Meal added to your plan.');
    }

    public function destroy(Request $request, int $entry)
    {
        $date = DB::transaction(function () use ($request, $entry) {
            DB::table('users')
                ->where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $meal = MealPlanEntry::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($entry);

            $date = $meal->plan_date->format('Y-m-d');

            $meal->delete();

            return $date;
        });

        return redirect()
            ->route('meal-plan.index', ['date' => $date])
            ->with('status', 'Meal removed from the plan. Your saved meal remains.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'meal' => $message,
        ]);
    }
}
