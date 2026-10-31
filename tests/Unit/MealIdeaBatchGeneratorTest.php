<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Meals\MealIdeaBatchGenerator;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class MealIdeaBatchGeneratorTest extends TestCase
{
    private function candidate(string $name, bool $fits = true): array
    {
        return ['name' => $name, 'fits' => $fits, 'ingredients' => [['quantity_g' => 123.4]],
            'totals' => ['energy_kcal' => 500]];
    }

    private function runGeneration(array $responses, ?callable $contextCallback = null): array
    {
        $user = new User();
        $user->id = 1;
        $calls = [];
        $generator = Mockery::mock(MealIdeaGenerator::class);
        $context = ['signature' => 'current', 'meal_type' => 'lunch'];
        $generator->shouldReceive('context')->andReturnUsing($contextCallback ?? fn () => $context);
        $generator->shouldReceive('generate')->andReturnUsing(function ($user, $type, $count = 3, $avoid = []) use (&$calls, &$responses) {
            $calls[] = [$type, $count, $avoid];
            if (! count($responses)) { $this->fail('More AI calls than allowed.'); }
            $response = array_shift($responses);
            if ($response instanceof ValidationException) { throw $response; }
            return ['signature' => 'current', 'meal_type' => 'lunch', 'ideas' => $response];
        });
        $portions = Mockery::mock(MealIdeaPortionBalancer::class);
        $portions->shouldReceive('balanceBatch')->andReturnUsing(function ($batch, $context, $allowEmpty) {
            $this->assertTrue($allowEmpty);
            $batch['ideas'] = array_values(array_filter($batch['ideas'], fn ($row) => $row['fits']));
            return $batch;
        });
        $batch = (new MealIdeaBatchGenerator($generator, $portions))->generate($user, 'lunch');
        return [$batch, $calls];
    }

    public function test_three_suitable_ideas_make_only_one_request(): void
    {
        [$batch, $calls] = $this->runGeneration([[$this->candidate('A'), $this->candidate('B'), $this->candidate('C')]]);
        $this->assertCount(3, $batch['ideas']);
        $this->assertCount(1, $calls);
        $this->assertSame(1, $batch['generation_attempts']);
    }

    public function test_partial_batch_uses_full_permitted_replacement_pool_and_keeps_original_portions(): void
    {
        $first = $this->candidate('Keep me');
        [$batch, $calls] = $this->runGeneration([
            [$first, $this->candidate('Rejected A', false), $this->candidate('Rejected B', false)],
            [$this->candidate('Replacement A'), $this->candidate('Replacement B')],
        ]);
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame($first, $batch['ideas'][0]);
        $this->assertSame(3, $calls[1][1]);
        $this->assertSame(['Keep me', 'Rejected A', 'Rejected B'], $calls[1][2]);
        $this->assertSame(2, $batch['rejected_count']);
    }

    public function test_two_suitable_ideas_still_request_three_replacement_candidates(): void
    {
        [$batch, $calls] = $this->runGeneration([
            [$this->candidate('A'), $this->candidate('B'), $this->candidate('No fat', false)],
            [$this->candidate('C')],
        ]);
        $this->assertSame(3, $calls[1][1]);
        $this->assertCount(3, $batch['ideas']);
    }

    public function test_zero_suitable_first_pass_can_be_replaced(): void
    {
        [$batch, $calls] = $this->runGeneration([
            [$this->candidate('Bad', false)],
            [$this->candidate('A'), $this->candidate('B'), $this->candidate('C')],
        ]);
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame(3, $calls[1][1]);
    }

    public function test_repeated_names_are_not_counted_as_extra_options(): void
    {
        [$batch, $calls] = $this->runGeneration([
            [$this->candidate('A'), $this->candidate('B'), $this->candidate('Bad', false)],
            [$this->candidate(' a '), $this->candidate('B')],
        ]);
        $this->assertCount(2, $batch['ideas']);
        $this->assertCount(2, $calls);
        $this->assertSame(2, $batch['duplicate_count']);
    }

    public function test_remaining_failures_stop_after_one_extra_request(): void
    {
        [$batch, $calls] = $this->runGeneration([
            [$this->candidate('A'), $this->candidate('Bad', false)],
            [$this->candidate('Still bad', false)],
        ]);
        $this->assertCount(1, $batch['ideas']);
        $this->assertCount(2, $calls);
        $this->assertFalse($batch['replacement_unavailable']);
    }

    public function test_two_failed_portion_checks_return_no_unchecked_options(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No ideas met this meal');
        $this->runGeneration([[$this->candidate('Bad', false)], [$this->candidate('Worse', false)]]);
    }

    public function test_provider_failure_on_first_request_is_not_retried(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Provider unavailable');
        $this->runGeneration([ValidationException::withMessages(['generation' => 'Provider unavailable'])]);
    }

    public function test_provider_failure_on_replacement_keeps_checked_options(): void
    {
        [$batch, $calls] = $this->runGeneration([
            [$this->candidate('Keep me'), $this->candidate('Bad', false)],
            ValidationException::withMessages(['generation' => 'Provider unavailable']),
        ]);
        $this->assertCount(1, $batch['ideas']);
        $this->assertTrue($batch['replacement_unavailable']);
        $this->assertCount(2, $calls);
    }

    public function test_provider_failure_on_replacement_with_no_passed_options_is_clear(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('replacement request did not complete');
        $this->runGeneration([
            [$this->candidate('Bad', false)],
            ValidationException::withMessages(['generation' => 'Provider unavailable']),
        ]);
    }

    public function test_context_change_discards_results_before_another_paid_request(): void
    {
        $reads = 0;
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('changed during generation');
        $this->runGeneration([[$this->candidate('A')]], function () use (&$reads) {
            return ['meal_type' => 'lunch', 'signature' => ++$reads >= 3 ? 'changed' : 'current'];
        });
    }

    public function test_context_change_after_extra_request_rejects_even_the_earlier_options(): void
    {
        $reads = 0;
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('changed during generation');
        $this->runGeneration([[$this->candidate('A')], [$this->candidate('B')]], function () use (&$reads) {
            return ['meal_type' => 'lunch', 'signature' => ++$reads >= 5 ? 'changed' : 'current'];
        });
    }
}
