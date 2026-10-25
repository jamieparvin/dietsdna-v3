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
    ) {
    }

    public function generate(User $user, string $mealType): array
    {
        $context = $this->generator->context($user, $mealType);
        if ($context['meal_type'] !== $mealType) {
            $this->fail('Choose a meal type in your current daily meal pattern.');
        }
        $accepted = [];
        $names = [];
        $avoid = [];
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
                    : $this->generator->generate($user, $mealType, 3 - count($accepted), $avoid);
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
            foreach ($raw['ideas'] as $idea) {
                $avoid[] = $idea['name'];
            }
            foreach ($checked['ideas'] as $idea) {
                $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($idea['name'])));
                if (isset($names[$key])) {
                    $duplicates++;
                    continue;
                }
                if (count($accepted) >= 3) {
                    break;
                }
                $names[$key] = true;
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
            'duplicate_count' => $duplicates,
            'generation_attempts' => $attempts,
            'replacement_unavailable' => $replacementUnavailable,
            'portion_version' => MealIdeaPortionBalancer::VERSION,
            'balancer_version' => MealPortionBalancer::VERSION,
        ]);
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
