<?php

namespace App\Services\Meals;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Individual ideas only: at most two sequential AI requests per submission. */
class MealIdeaBatchGenerator
{
    public function __construct(
        private readonly MealIdeaGenerator $generator,
        private readonly MealIdeaPortionBalancer $portions,
    ) {}

    public function generate(User $user, string $mealType): array
    {
        $context = $this->generator->context($user, $mealType);
        if ($context['meal_type'] !== $mealType) {
            $this->fail('Choose a meal type in your current daily meal pattern.');
        }
        $accepted = [];
        $names = [];
        $recipes = [];
        $avoid = [];
        $feedback = [];
        $rejectionDetails = [];
        $attempts = 0;
        $candidates = 0;
        $rejected = 0;
        $duplicates = 0;
        $replacementUnavailable = false;
        $batch = [];

        for ($attempt = 0; $attempt < 2 && count($accepted) < 3; $attempt++) {
            $this->assertCurrent($user, $mealType, $context);
            $attempts++;
            try {
                $raw = $attempt === 0
                    ? $this->generator->generate($user, $mealType)
                    // Use the permitted three-candidate request for the last chance,
                    // rather than leave one empty slot dependent on one candidate.
                    : $this->generator->generate($user, $mealType, 3, $avoid, $feedback);
            } catch (ValidationException $exception) {
                // Do not retry transport, provider or schema failures. The only
                // extra request follows completed candidates failing portion checks.
                $this->assertCurrent($user, $mealType, $context);
                if ($attempt === 0) {
                    throw $exception;
                }
                $replacementUnavailable = true;
                if (! $accepted) {
                    $this->fail('The replacement request did not complete and no suitable new ideas are available. Your previous ideas and saved meals have not changed. Try again later.');
                }
                break;
            }
            $this->assertCurrent($user, $mealType, $context);
            // Signature/type/malformed-batch errors propagate: they are not
            // interpreted as a reason to spend another AI request.
            $checked = $this->portions->balanceBatch($raw, $context, true);
            $batch = $checked;
            $candidates += count($raw['ideas']);
            $rejected += count($raw['ideas']) - count($checked['ideas']);
            $feedback = $checked['portion_rejections'] ?? [];
            array_push($rejectionDetails, ...$feedback);
            foreach ($raw['ideas'] as $idea) {
                $avoid[] = $idea['name'];
            }
            foreach ($checked['ideas'] as $idea) {
                $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($idea['name'])));
                $recipe = $this->recipeKey($idea);
                if (isset($names[$key]) || ($recipe !== null && isset($recipes[$recipe]))) {
                    $duplicates++;

                    continue;
                }
                if (count($accepted) >= 3) {
                    break;
                }
                $names[$key] = true;
                if ($recipe !== null) {
                    $recipes[$recipe] = true;
                }
                $accepted[] = $idea;
            }
            $avoid = array_values(array_unique($avoid));
        }

        $this->assertCurrent($user, $mealType, $context);
        if (! $accepted) {
            $this->fail('No ideas met this meal’s calorie and macro guide within the portion limits after one replacement attempt. Your previous ideas and saved meals have not changed. Try different meal ideas or review your eligible food choices.');
        }

        // Accepted portions from the first pass are never rebalanced on retry.
        // Publish the combined batch only once; no session or database writes here.
        return array_replace($batch, [
            'signature' => $context['signature'],
            'meal_type' => $mealType,
            'created_at' => now()->timestamp,
            'ideas' => array_values($accepted),
            'requested_count' => 3,
            'candidate_count' => $candidates,
            'rejected_count' => $rejected,
            'portion_rejections' => $rejectionDetails,
            'duplicate_count' => $duplicates,
            'generation_attempts' => $attempts,
            'replacement_unavailable' => $replacementUnavailable,
            'portion_version' => MealIdeaPortionBalancer::VERSION,
            'balancer_version' => MealPortionBalancer::VERSION,
        ]);
    }

    /** Ignore titles and row order when detecting an otherwise identical recipe. */
    private function recipeKey(array $idea): ?string
    {
        if (! isset($idea['instructions'])) {
            return null;
        }
        $rows = array_map(fn ($row) => [
            $row['ingredient_id'], (float) $row['quantity_g'],
        ], $idea['ingredients']);
        sort($rows);

        return hash('sha256', json_encode([
            $rows, mb_strtolower(preg_replace('/\s+/u', ' ', trim($idea['instructions']))),
        ], JSON_THROW_ON_ERROR));
    }

    private function assertCurrent(User $user, string $mealType, array $context): void
    {
        $fresh = $this->generator->context($user, $mealType);
        if ($fresh['signature'] !== $context['signature'] || $fresh['meal_type'] !== $context['meal_type']) {
            $this->fail('Your profile, preferences or ingredients changed during generation. Generate fresh ideas.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['generation' => $message]);
    }
}
