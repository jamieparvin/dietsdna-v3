<?php

namespace App\Http\Controllers;

use App\Services\Meals\MealIdeaGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MealIdeaController extends Controller
{
    public function index(
        Request $request,
        MealIdeaGenerator $generator
    ) {
        $validated = $request->validate([
            'meal_type' => [
                'nullable',
                Rule::in(['breakfast', 'lunch', 'dinner', 'snack']),
            ],
        ]);

        $context = null;
        $blocked = null;
        $batch = null;

        $saved = $request->session()->get(
            $this->sessionKey($request)
        );

        $selectedType = $validated['meal_type']
            ?? (is_array($saved) ? ($saved['meal_type'] ?? 'lunch') : 'lunch');

        try {
            $context = $generator->context(
                $request->user(),
                $selectedType
            );

            if (
                is_array($saved)
                && ($saved['signature'] ?? null) === $context['signature']
                && ($saved['meal_type'] ?? null) === $context['meal_type']
                && ($saved['created_at'] ?? 0) >= now()->subMinutes(30)->timestamp
            ) {
                $batch = $saved;
            }
        } catch (ValidationException $exception) {
            $blocked = collect($exception->errors())
                ->flatten()
                ->first();
        }

        $savedIdeaIndexes = [];

        if ($batch && $context) {
            $hashes = [];

            foreach ($batch['ideas'] as $index => $idea) {
                unset($idea['calorie_difference']);

                foreach ($idea['ingredients'] as &$row) {
                    $matches = $context['ingredients']->filter(
                        fn ($ingredient) =>
                            $ingredient->name === $row['name']
                            && $ingredient->preparation_state
                                === $row['preparation_state']
                    );

                    if ($matches->count() !== 1) {
                        unset($row);
                        continue 2;
                    }

                    $ingredient = $matches->first();

                    $row['ingredient_id'] = (int) $ingredient->id;
                    $row['food_key'] = MealIdeaGenerator::foodKey($ingredient);
                }

                unset($row);

                $hashes[$index] = hash(
                    'sha256',
                    json_encode($idea, JSON_THROW_ON_ERROR)
                );
            }

            $existingHashes = \App\Models\SavedMeal::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('content_hash', array_values($hashes))
                ->pluck('content_hash')
                ->all();

            foreach ($hashes as $index => $hash) {
                if (in_array($hash, $existingHashes, true)) {
                    $savedIdeaIndexes[] = $index;
                }
            }
        }

        return view('meal-ideas.index', compact(
            'context',
            'blocked',
            'batch',
            'savedIdeaIndexes'
        ));
    }

    public function store(
        Request $request,
        MealIdeaGenerator $generator
    ) {
        $data = $request->validate([
            'meal_type' => [
                'required',
                Rule::in(['breakfast', 'lunch', 'dinner', 'snack']),
            ],
        ]);

        $lock = Cache::lock(
            'meal-ideas:user:'.$request->user()->id,
            120
        );

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'generation' =>
                    'Meal ideas are already being generated. Please wait.',
            ]);
        }

        try {
            set_time_limit(90);

            $batch = $generator->generate(
                $request->user(),
                $data['meal_type']
            );

            $request->session()->put(
                $this->sessionKey($request),
                $batch
            );

            return redirect()
                ->route('meal-ideas.index')
                ->with('status', 'Your meal ideas are ready.');
        } finally {
            $lock->release();
        }
    }

    private function sessionKey(Request $request): string
    {
        return 'meal_ideas.user_'.$request->user()->id;
    }
}