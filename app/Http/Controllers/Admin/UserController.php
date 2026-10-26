<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealPlanSlots;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Nutrition\SnapshotNutrition;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $search = trim($validated['search'] ?? '');

        $users = User::query()
            ->select(['id', 'name', 'email', 'is_admin', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function show(
        Request $request,
        User $user,
        NutritionTargetCalculator $calculator
    ): View {
        // These routes must also remain inside the protected admin group.
        abort_unless($request->user()?->is_admin, 403);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = CarbonImmutable::now('Europe/London')->startOfDay();

        $selectedDate = isset($validated['date'])
            ? CarbonImmutable::parse(
                $validated['date'],
                'Europe/London'
            )->startOfDay()
            : $today;

        $weekStart = $selectedDate->startOfWeek(1);
        $weekEnd = $weekStart->addDays(6);

        $profile = $user->nutritionProfile()->first();
        $targets = $calculator->calculate($profile);

        /*
         * Read preferences for the selected customer, not the
         * administrator viewing this page.
         */
        $savedPreferences = DB::table('user_food_preferences')
            ->where('user_id', $user->id)
            ->pluck('preference', 'food_key');

        $foodGroups = $savedPreferences->isEmpty()
            ? collect()
            : Ingredient::query()
                ->orderBy('category')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->groupBy(
                    fn ($ingredient) => $ingredient->preferenceFoodKey()
                );

        $foodPreferences = $savedPreferences
            ->map(function ($preference, $key) use ($foodGroups) {
                $variants = $foodGroups->get($key, collect());
                $first = $variants->first();

                $name = $first
                    ? (trim((string) $first->preference_food)
                        ?: trim($first->name))
                    : 'Food no longer in the ingredient library';

                $category = $first?->category ?? 'Unavailable foods';

                if (
                    $first
                    && mb_strtolower(trim($category)) === 'protein & dairy'
                ) {
                    $category = match (
                        mb_strtolower(trim((string) $first->sub_category))
                    ) {
                        'dairy', 'eggs' => 'Dairy',
                        'plant proteins' => 'Plant proteins',
                        default => $category,
                    };
                }

                return [
                    'food_key' => $key,
                    'name' => $name,
                    'category' => $category,
                    'preference' => $preference,
                    'is_available' => $variants->contains(
                        fn ($ingredient) => (bool) $ingredient->is_active
                    ),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $likedFoods = $foodPreferences
            ->where('preference', 'preferred')
            ->values();

        $excludedFoods = $foodPreferences
            ->where('preference', 'excluded')
            ->values();

        $savedMealCount = SavedMeal::query()
            ->where('user_id', $user->id)
            ->count();

        $savedMealCounts = SavedMeal::query()
            ->where('user_id', $user->id)
            ->select('meal_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('meal_type')
            ->pluck('total', 'meal_type');

        /*
         * Use an exclusive next-Monday boundary so Sunday entries
         * are included consistently.
         */
        $weekEntries = MealPlanEntry::query()
            ->where('user_id', $user->id)
            ->where('plan_date', '>=', $weekStart->toDateString())
            ->where('plan_date', '<', $weekStart->addWeek()->toDateString())
            ->orderBy('plan_date')
            ->orderBy('id')
            ->get();

        $slots = MealPlanSlots::forCount(
            (int) $profile?->meals_per_day,
            (int) ($profile?->snacks_per_day ?? 0)
        );

        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $dayDate = $weekStart->addDays($offset);

            $dayEntries = $weekEntries
                ->filter(
                    fn ($entry) =>
                        $entry->plan_date->format('Y-m-d')
                        === $dayDate->toDateString()
                )
                ->sortBy(
                    fn ($entry) =>
                        MealPlanSlots::displayOrder($entry->slot)
                )
                ->values();

            $days[] = [
                'date' => $dayDate,
                'entries' => $dayEntries,
                'totals' => SnapshotNutrition::aggregate(
                    $dayEntries->pluck('snapshot')
                ),
            ];
        }

        $firstWeightEntry = $user->weightEntries()
            ->orderBy('recorded_on')
            ->orderBy('id')
            ->first();

        $latestWeightEntry = $user->weightEntries()
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->first();

        $weightEntryCount = $user->weightEntries()->count();

        $weightChange = $firstWeightEntry && $latestWeightEntry
            ? (float) $latestWeightEntry->weight_kg
                - (float) $firstWeightEntry->weight_kg
            : null;

        $recentWeights = $user->weightEntries()
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'recorded_on', 'weight_kg']);

        $recentDrafts = WeeklyMealDraft::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'start_date',
                'status',
                'created_at',
            ]);

        return view('admin.users.show', compact(
            'user',
            'profile',
            'targets',
            'today',
            'selectedDate',
            'weekStart',
            'weekEnd',
            'slots',
            'days',
            'likedFoods',
            'excludedFoods',
            'savedMealCount',
            'savedMealCounts',
            'firstWeightEntry',
            'latestWeightEntry',
            'weightEntryCount',
            'weightChange',
            'recentWeights',
            'recentDrafts'
        ));
    }
}