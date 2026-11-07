<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPlanSlots;
use App\Services\Meals\MealPreparationTime;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\WeeklyMealSchedule;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealPlanSwapController extends Controller
{
    public function create(Request $request, int $entry, MealIdeaGenerator $generator, PlannedMealSnapshot $snapshots)
    {
        $data = $request->validate(['type' => ['nullable', 'in:all,breakfast,lunch,dinner,snack'], 'page' => ['nullable', 'integer', 'min:1']]);
        $entry = MealPlanEntry::query()->where('user_id', $request->user()->id)->findOrFail($entry);
        $type = $data['type'] ?? WeeklyMealSchedule::typeForSlot($entry->slot);
        $blocked = null;
        $eligibleMeals = collect();

        try {
            $context = $generator->context($request->user(), WeeklyMealSchedule::typeForSlot($entry->slot));
            $eligibleMeals = SavedMeal::query()->where('user_id', $request->user()->id)
                ->when($type !== 'all', fn ($query) => $query->where('meal_type', $type))
                ->where('id', '!=', $entry->saved_meal_id)->latest('id')->get()
                ->filter(fn ($meal) => $snapshots->eligible($meal->snapshot, $context['ingredients']))
                ->sort(fn ($a, $b) => MealPreparationTime::score($a->snapshot, $context['cooking_minutes'] ?? null) <=> MealPreparationTime::score($b->snapshot, $context['cooking_minutes'] ?? null))->values();
        } catch (ValidationException $exception) {
            $blocked = collect($exception->errors())->flatten()->first();
        }

        $page = (int) ($data['page'] ?? 1);
        $meals = new LengthAwarePaginator($eligibleMeals->forPage($page, 12)->values(), $eligibleMeals->count(), 12, $page, [
            'path' => $request->url(), 'query' => $request->except('page'),
        ]);
        $entryToken = $snapshots->entryToken($entry);

        return view('meal-plan.swap', compact('entry', 'meals', 'type', 'blocked', 'entryToken'));
    }

    public function update(Request $request, int $entry, MealIdeaGenerator $generator, PlannedMealSnapshot $snapshots)
    {
        $data = $request->validate([
            'saved_meal_id' => ['required', 'integer'],
            'entry_token' => ['required', 'string', 'size:64'],
        ]);

        $date = DB::transaction(function () use ($request, $entry, $generator, $snapshots, $data) {
            $user = $request->user();
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $planned = MealPlanEntry::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($entry);

            if (! hash_equals($snapshots->entryToken($planned), $data['entry_token'])) {
                throw ValidationException::withMessages(['meal' => 'This planned meal changed in another tab. Reload the swap page before choosing again.']);
            }

            $profile = $user->nutritionProfile()->first();
            $slots = MealPlanSlots::forCount(
                (int) $profile?->meals_per_day,
                (int) ($profile?->snacks_per_day ?? 0)
            );

            if (! isset($slots[$planned->slot])) {
                throw ValidationException::withMessages(['meal' => 'This slot belongs to a previous meal pattern. Remove it from the plan instead.']);
            }

            $replacement = SavedMeal::query()->where('user_id', $user->id)->findOrFail($data['saved_meal_id']);
            $context = $generator->context($user, $replacement->meal_type);

            if (! $snapshots->eligible($replacement->snapshot, $context['ingredients'])) {
                throw ValidationException::withMessages(['meal' => 'This saved meal no longer matches your current ingredient availability or food preferences.']);
            }

            $planned->update(['saved_meal_id' => $replacement->id, 'snapshot' => $replacement->snapshot]);

            return $planned->plan_date->format('Y-m-d');
        });

        return redirect()->route('meal-plan.index', ['date' => $date])->with('status', 'Meal swapped. Saved portions and the rest of your plan were kept unchanged.');
    }
}
