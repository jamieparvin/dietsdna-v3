<?php

namespace App\Http\Controllers;

use App\Models\SavedMeal;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaBatchGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\PlannedMealSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MealIdeaController extends Controller
{
    public function index(Request $request, MealIdeaGenerator $generator, MealIdeaPortionBalancer $portions, PlannedMealSnapshot $snapshots)
    {
        $validated = $request->validate(['meal_type' => ['nullable', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])]]);
        $context = $blocked = $batch = $portionNotice = null;
        $saved = $request->session()->get($this->sessionKey($request));
        $selectedType = $validated['meal_type'] ?? (is_array($saved) ? ($saved['meal_type'] ?? 'lunch') : 'lunch');
        try {
            $context = $generator->context($request->user(), $selectedType);
            if (is_array($saved) && ! $portions->isCurrentBatch($saved)) {
                // Do not silently resize old cards or previously saved meals on GET.
                $request->session()->forget($this->sessionKey($request));
                $portionNotice = 'Your previous temporary ideas need the new portion check. Generate fresh ideas below. Your Saved meals have not changed.';
            } elseif (is_array($saved)
                && ($saved['signature'] ?? null) === $context['signature']
                && ($saved['meal_type'] ?? null) === $context['meal_type']
                && ($saved['created_at'] ?? 0) >= now()->subMinutes(30)->timestamp) {
                $batch = $saved;
            }
        } catch (ValidationException $exception) {
            $blocked = collect($exception->errors())->flatten()->first();
        }
        $savedIdeaIndexes = [];
        if ($batch && $context) {
            $hashes = [];
            foreach ($batch['ideas'] as $index => $idea) {
                try {
                    $hashes[$index] = $snapshots->hash($snapshots->fromIdea($idea, $context['ingredients']));
                } catch (ValidationException $exception) {
                    continue;
                }
            }
            $existing = SavedMeal::query()->where('user_id', $request->user()->id)
                ->whereIn('content_hash', array_values($hashes))->pluck('content_hash')->all();
            foreach ($hashes as $index => $hash) {
                if (in_array($hash, $existing, true)) {
                    $savedIdeaIndexes[] = $index;
                }
            }
        }
        return view('meal-ideas.index', compact('context', 'blocked', 'batch', 'savedIdeaIndexes', 'portionNotice'));
    }

    public function store(Request $request, MealIdeaBatchGenerator $batches)
    {
        $data = $request->validate(['meal_type' => ['required', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])]]);
        $lock = Cache::lock('meal-ideas:user:'.$request->user()->id, 240);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['generation' => 'Meal ideas are already being generated. Please wait.']);
        }
        try {
            set_time_limit(180);
            $batch = $batches->generate($request->user(), $data['meal_type']);
            $request->session()->put($this->sessionKey($request), $batch);
            return redirect()->route('meal-ideas.index', ['meal_type' => $data['meal_type']])
                ->with('status', 'Your portion-checked meal ideas are ready.');
        } finally {
            $lock->release();
        }
    }

    private function sessionKey(Request $request): string
    {
        return 'meal_ideas.user_'.$request->user()->id;
    }
}
