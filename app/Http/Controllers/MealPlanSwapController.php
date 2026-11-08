<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Services\Meals\MealIdeaBatchGenerator;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\MealPlanSlots;
use App\Services\Meals\MealPreparationTime;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\WeeklyMealSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MealPlanSwapController extends Controller
{
    public function create(Request $request, int $entry, MealIdeaGenerator $generator, PlannedMealSnapshot $snapshots, MealIdeaPortionBalancer $portions): View
    {
        $data = $request->validate(['type' => ['nullable', 'in:all,breakfast,lunch,dinner,snack'], 'page' => ['nullable', 'integer', 'min:1']]);
        $entry = MealPlanEntry::query()->where('user_id', $request->user()->id)->findOrFail($entry);
        $mealType = WeeklyMealSchedule::typeForSlot($entry->slot);
        $type = $data['type'] ?? $mealType;
        $blocked = $batch = $batchNotice = null;
        $eligibleMeals = collect();
        $entryToken = $snapshots->entryToken($entry);

        try {
            $this->assertSlot($request, $entry);
            $context = $generator->context($request->user(), $mealType);
            $eligibleMeals = SavedMeal::query()->where('user_id', $request->user()->id)
                ->when($type !== 'all', fn ($query) => $query->where('meal_type', $type))
                ->where('id', '!=', $entry->saved_meal_id)->latest('id')->get()
                ->filter(fn ($meal) => $snapshots->eligible($meal->snapshot, $context['ingredients']) && ! $snapshots->sameRecipe($meal->snapshot, $entry->snapshot))
                ->sort(fn ($a, $b) => MealPreparationTime::score($a->snapshot, $context['cooking_minutes'] ?? null) <=> MealPreparationTime::score($b->snapshot, $context['cooking_minutes'] ?? null))->values();
            if ($request->session()->has($this->sessionKey($request, $entry->id))) {
                try {
                    $batch = $this->reviewBatch($request, $entry, $context, $snapshots, $portions);
                } catch (ValidationException $exception) {
                    $request->session()->forget($this->sessionKey($request, $entry->id));
                    $batchNotice = collect($exception->errors())->flatten()->first();
                }
            }
        } catch (ValidationException $exception) {
            $blocked = collect($exception->errors())->flatten()->first();
        }

        $page = (int) ($data['page'] ?? 1);
        $meals = new LengthAwarePaginator($eligibleMeals->forPage($page, 12)->values(), $eligibleMeals->count(), 12, $page, [
            'path' => $request->url(), 'query' => $request->except('page'),
        ]);

        return view('meal-plan.swap', compact('entry', 'meals', 'type', 'mealType', 'blocked', 'entryToken', 'batch', 'batchNotice'));
    }

    public function generate(Request $request, int $entry, MealIdeaBatchGenerator $batches, PlannedMealSnapshot $snapshots): RedirectResponse
    {
        $planned = MealPlanEntry::query()->where('user_id', $request->user()->id)->findOrFail($entry);
        $data = $request->validate(['entry_token' => ['required', 'string', 'size:64']]);
        $lock = Cache::lock('meal-ideas:user:'.$request->user()->id, 240);
        if (! $lock->get()) {
            return redirect()->route('meal-plan.swap', $entry)->withErrors(['generation' => 'Meal ideas are already being generated. Please wait, then try again.']);
        }
        try {
            $this->assertSlot($request, $planned);
            $this->assertEntry($planned, $data['entry_token'], $snapshots);
            $request->session()->forget($this->sessionKey($request, $entry));
            set_time_limit(180);
            $batch = $batches->generate($request->user(), WeeklyMealSchedule::typeForSlot($planned->slot), [$planned->snapshot]);
            $this->assertEntry($planned->fresh(), $data['entry_token'], $snapshots);
            $request->session()->put($this->sessionKey($request, $entry), array_replace($batch, [
                'entry_token' => $data['entry_token'], 'batch_token' => Str::random(64),
            ]));
        } catch (ValidationException $exception) {
            return redirect()->route('meal-plan.swap', $entry)->withErrors($exception->errors());
        } finally {
            $lock->release();
        }

        return redirect()->route('meal-plan.swap', $entry)->with('status', 'Review your suggestions below. Your planned meal stays in place until you choose a replacement.');
    }

    public function update(Request $request, int $entry, MealIdeaGenerator $generator, PlannedMealSnapshot $snapshots, MealIdeaPortionBalancer $portions): RedirectResponse
    {
        $data = $request->validate([
            'saved_meal_id' => ['nullable', 'required_without:idea_index', 'prohibits:idea_index', 'integer'],
            'idea_index' => ['nullable', 'required_without:saved_meal_id', 'integer', 'between:0,2'],
            'batch_token' => ['nullable', 'required_with:idea_index', 'string', 'size:64'],
            'entry_token' => ['required', 'string', 'size:64'],
        ]);
        $date = DB::transaction(function () use ($request, $entry, $generator, $snapshots, $portions, $data) {
            $user = $request->user();
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $planned = MealPlanEntry::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($entry);
            $this->assertEntry($planned, $data['entry_token'], $snapshots);
            $this->assertSlot($request, $planned);
            $mealType = WeeklyMealSchedule::typeForSlot($planned->slot);
            if (isset($data['saved_meal_id'])) {
                $replacement = SavedMeal::query()->where('user_id', $user->id)->findOrFail($data['saved_meal_id']);
                $context = $generator->context($user, $replacement->meal_type);
                $snapshot = $replacement->snapshot;
                $savedId = $replacement->id;
            } else {
                $context = $generator->context($user, $mealType);
                $batch = $this->reviewBatch($request, $planned, $context, $snapshots, $portions);
                if (! hash_equals($batch['batch_token'], $data['batch_token']) || ! isset($batch['ideas'][$data['idea_index']])) {
                    $this->fail('These suggestions changed or expired. Generate new ideas before choosing a replacement.');
                }
                $idea = $batch['ideas'][$data['idea_index']];
                $portions->assertSavable($idea, $context);
                $snapshot = $snapshots->fromIdea($idea, $context['ingredients']);
                $savedId = null;
            }
            if (! $snapshots->eligible($snapshot, $context['ingredients'])) {
                $this->fail('This meal no longer matches your current ingredient availability or food preferences.');
            }
            if ($snapshots->sameRecipe($snapshot, $planned->snapshot)) {
                $this->fail('Choose a different recipe. A copy with different portions is already planned for this slot.');
            }
            $planned->update(['saved_meal_id' => $savedId, 'snapshot' => $snapshot]);

            return $planned->plan_date->format('Y-m-d');
        });
        $request->session()->forget($this->sessionKey($request, $entry));

        return redirect()->route('meal-plan.index', ['date' => $date])->with('status', 'Meal swapped. Only the selected day and meal slot were updated.');
    }

    private function reviewBatch(Request $request, MealPlanEntry $entry, array $context, PlannedMealSnapshot $snapshots, MealIdeaPortionBalancer $portions): array
    {
        $batch = $request->session()->get($this->sessionKey($request, $entry->id));
        if (! is_array($batch) || ! $portions->isCurrentBatch($batch)
            || ($batch['entry_token'] ?? null) !== $snapshots->entryToken($entry)
            || ($batch['signature'] ?? null) !== $context['signature']
            || ($batch['meal_type'] ?? null) !== WeeklyMealSchedule::typeForSlot($entry->slot)
            || ! is_string($batch['batch_token'] ?? null)
            || ($batch['created_at'] ?? 0) < now()->subMinutes(30)->timestamp) {
            $this->fail('These suggestions are no longer current. Generate fresh ideas. Your planned meal has not changed.');
        }

        return $batch;
    }

    private function assertEntry(MealPlanEntry $entry, string $token, PlannedMealSnapshot $snapshots): void
    {
        if (! hash_equals($snapshots->entryToken($entry), $token)) {
            $this->fail('This planned meal changed in another tab. Reload the swap page before choosing again.');
        }
    }

    private function assertSlot(Request $request, MealPlanEntry $entry): void
    {
        $profile = $request->user()->nutritionProfile()->first();
        $slots = MealPlanSlots::forCount((int) $profile?->meals_per_day, (int) ($profile?->snacks_per_day ?? 0));
        if (! isset($slots[$entry->slot])) {
            $this->fail('This slot belongs to a previous meal pattern. Remove it from the plan instead.');
        }
    }

    private function sessionKey(Request $request, int $entry): string
    {
        return 'meal_swap.user_'.$request->user()->id.'.entry_'.$entry;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['meal' => $message]);
    }
}
