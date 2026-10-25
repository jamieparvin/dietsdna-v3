<?php

namespace App\Http\Controllers;

use App\Models\SavedMeal;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\MealPlanSlots;
use App\Services\Meals\PlannedMealSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavedMealController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $validated['date'] ?? now('Europe/London')->toDateString();
        $profile = $request->user()->nutritionProfile()->first();
        $slots = MealPlanSlots::forCount((int) $profile?->meals_per_day, (int) ($profile?->snacks_per_day ?? 0));
        $sections = ['breakfast' => 'Breakfast options', 'lunch' => 'Lunch options', 'dinner' => 'Dinner options', 'snack' => 'Snack options'];
        $mealGroups = [];
        foreach ($sections as $type => $label) {
            $meals = SavedMeal::query()->where('user_id', $request->user()->id)->where('meal_type', $type)
                ->latest('id')->paginate(6, ['*'], $type.'_page')->withQueryString()->fragment($type);
            $mealGroups[$type] = ['label' => $label, 'meals' => $meals];
        }
        return view('saved-meals.index', compact('mealGroups', 'slots', 'date'));
    }

    public function store(Request $request, MealIdeaGenerator $generator, MealIdeaPortionBalancer $portions, PlannedMealSnapshot $snapshots)
    {
        $data = $request->validate([
            'idea_index' => ['required', 'integer', 'between:0,2'],
            'batch_token' => ['required', 'string', 'size:64'],
        ]);
        DB::transaction(function () use ($request, $generator, $portions, $snapshots, $data) {
            $user = $request->user();
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $batch = $request->session()->get('meal_ideas.user_'.$user->id);
            if (! is_array($batch) || ($batch['created_at'] ?? 0) < now()->subMinutes(30)->timestamp) {
                $this->fail('These temporary ideas have expired. Generate a new batch.');
            }
            $token = hash('sha256', json_encode($batch, JSON_THROW_ON_ERROR));
            if (! hash_equals($token, $data['batch_token'])) {
                $this->fail('A different batch is now open. Reload Meal ideas and select the meal again.');
            }
            if (! $portions->isCurrentBatch($batch)) {
                $this->fail('These ideas need the new portion check. Generate a fresh batch before saving.');
            }
            $context = $generator->context($user, $batch['meal_type']);
            if ($context['signature'] !== ($batch['signature'] ?? null) || $context['meal_type'] !== $batch['meal_type']) {
                $this->fail('Your profile, preferences or ingredients changed. Generate fresh ideas before saving.');
            }
            $idea = $batch['ideas'][$data['idea_index']] ?? null;
            if (! is_array($idea)) {
                $this->fail('That meal idea is no longer available.');
            }
            // Check the stored amounts and recomputed nutrition; NEVER optimize on save.
            $portions->assertSavable($idea, $context);
            $snapshot = $snapshots->fromIdea($idea, $context['ingredients']);
            SavedMeal::query()->firstOrCreate([
                'user_id' => $user->id, 'content_hash' => $snapshots->hash($snapshot),
            ], ['meal_type' => $batch['meal_type'], 'snapshot' => $snapshot]);
        });
        return redirect()->route('meal-ideas.index', [
            'meal_type' => $request->session()->get('meal_ideas.user_'.$request->user()->id.'.meal_type', 'lunch'),
        ])->with('status', 'Meal saved. You can save another idea below.');
    }

    public function destroy(Request $request, int $savedMeal)
    {
        $pages = ['breakfast_page', 'lunch_page', 'dinner_page', 'snack_page'];
        $rules = ['delete_confirmed' => ['required', 'accepted'], 'date' => ['nullable', 'date_format:Y-m-d']];
        foreach ($pages as $page) {
            $rules[$page] = ['nullable', 'integer', 'between:1,100000'];
        }
        $data = $request->validate($rules);
        [$type, $remaining] = DB::transaction(function () use ($request, $savedMeal) {
            $userId = $request->user()->id;
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $meal = SavedMeal::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($savedMeal);
            $type = $meal->meal_type;
            // ON DELETE SET NULL keeps dated entries and their immutable snapshots.
            $meal->delete();
            $remaining = SavedMeal::query()->where('user_id', $userId)->where('meal_type', $type)->count();
            return [$type, $remaining];
        });
        $query = [];
        if (! empty($data['date'])) {
            $query['date'] = $data['date'];
        }
        foreach ($pages as $page) {
            if (isset($data[$page])) {
                $query[$page] = (int) $data[$page];
            }
        }
        $labels = ['breakfast', 'lunch', 'dinner', 'snack'];
        if (in_array($type, $labels, true)) {
            $page = $type.'_page';
            $query[$page] = min($query[$page] ?? 1, max(1, (int) ceil($remaining / 6)));
        }
        $url = route('saved-meals.index', $query);
        if (in_array($type, $labels, true)) {
            $url .= '#'.$type;
        }
        return redirect()->to($url)->with('status', 'Saved meal deleted. Meals already in your plan have been kept.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['meal' => $message]);
    }
}
