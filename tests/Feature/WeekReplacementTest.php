<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSetupComplete;
use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPlanWeekState;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\WeeklyMealPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class WeekReplacementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private $ingredients;

    private int $generationCalls = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Use phpunit-weekly.xml with disposable in-memory SQLite.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // This suite exercises meal behavior with minimal profiles; onboarding has its own route coverage.
        $this->withoutMiddleware(EnsureSetupComplete::class);
        Http::preventStrayRequests();
        Queue::fake();
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create(['meals_per_day' => 3, 'snacks_per_day' => 0]);
        $this->ingredients = collect();
        foreach (['Fixture vegetable A', 'Fixture vegetable B'] as $i => $name) {
            $this->ingredients->push(Ingredient::query()->create([
                'food_code' => 'REPLACE-'.$i, 'name' => $name,
                'category' => 'Vegetables', 'sub_category' => 'Vegetables', 'preparation_state' => 'raw',
                'energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10, 'sugars_g' => 1,
                'fat_g' => 2, 'saturates_g' => 1, 'fibre_g' => 2, 'salt_g' => 0.01, 'is_active' => true,
            ]));
        }
        $this->mock(MealIdeaGenerator::class, function ($mock) {
            $mock->shouldReceive('context')->andReturnUsing(fn ($user, $type = 'lunch') => [
                'signature' => 'replacement-test-catalog', 'ingredients' => $this->ingredients,
                'meal_count' => (int) $user->nutritionProfile()->first()->meals_per_day,
                'snack_count' => (int) $user->nutritionProfile()->first()->snacks_per_day,
                'meal_type' => $type,
                'targets' => ['calories_kcal' => 600, 'protein_g' => 60, 'carbohydrate_g' => 48, 'fat_g' => 12],
            ]);
            $mock->shouldReceive('generate')->andReturnUsing(function ($user, $type) {
                $this->generationCalls++;

                return ['ideas' => array_map(fn ($n) => $this->idea($type.' option '.$n), [1, 2, 3])];
            });
        });
    }

    private function idea(string $name): array
    {
        return [
            'name' => $name, 'description' => 'Synthetic test meal.', 'instructions' => 'Prepare the listed ingredients.',
            'ingredients' => $this->ingredients->map(fn ($i) => ['name' => $i->name, 'preparation_state' => 'raw', 'quantity_g' => 50])->all(),
            'totals' => ['energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10,
                'sugars_g' => 1, 'fat_g' => 2, 'saturates_g' => 1, 'fibre_g' => 2, 'salt_g' => 0.01],
        ];
    }

    private function saved(User $user, string $name = 'Original saved meal'): SavedMeal
    {
        $helper = app(PlannedMealSnapshot::class);
        $snapshot = $helper->fromIdea($this->idea($name), $this->ingredients);

        return SavedMeal::query()->create(['user_id' => $user->id, 'content_hash' => $helper->hash($snapshot), 'meal_type' => 'lunch', 'snapshot' => $snapshot]);
    }

    private function entry(SavedMeal $meal, string $date = '2026-11-09', string $slot = 'lunch'): MealPlanEntry
    {
        return MealPlanEntry::query()->create(['user_id' => $meal->user_id, 'saved_meal_id' => $meal->id,
            'plan_date' => $date, 'slot' => $slot, 'snapshot' => $meal->snapshot]);
    }

    private function fillWeek(User $user): SavedMeal
    {
        $meal = $this->saved($user);
        foreach (range(0, 6) as $day) {
            $date = (new \DateTimeImmutable('2026-11-09'))->modify('+'.$day.' days')->format('Y-m-d');
            foreach (['breakfast', 'lunch', 'dinner'] as $slot) {
                $this->entry($meal, $date, $slot);
            }
        }

        return $meal;
    }

    private function startReplacement(): WeeklyMealDraft
    {
        $this->actingAs($this->owner)->post(route('weekly-meals.store'), ['start_date' => '2026-11-11', 'mode' => 'replace'])
            ->assertSessionHasNoErrors()->assertRedirect();

        return WeeklyMealDraft::query()->where('user_id', $this->owner->id)->latest('id')->firstOrFail();
    }

    private function readyReplacement(): WeeklyMealDraft
    {
        $draft = $this->startReplacement();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);

        return $draft->fresh();
    }

    private function ownPlan(): array
    {
        return MealPlanEntry::query()->where('user_id', $this->owner->id)->orderBy('id')->get()->toArray();
    }

    private function token(?User $user = null): string
    {
        return $this->actingAs($user ?? $this->owner)->get(route('meal-plan.clear.create', ['date' => '2026-11-12']))
            ->assertOk()->viewData('token');
    }

    public function test_replacement_can_be_requested_for_a_full_week_without_removing_anything(): void
    {
        $this->fillWeek($this->owner);
        $before = $this->ownPlan();
        $draft = $this->startReplacement();
        $this->assertSame('replace', $draft->mode);
        $this->assertSame('2026-11-09', $draft->start_date->toDateString());
        $this->assertNotEmpty($draft->baseline_fingerprint);
        $this->assertSame($before, $this->ownPlan());
        $this->assertSame(0, $this->generationCalls);
        Queue::assertPushed(GenerateWeeklyMealDraft::class, 1);
    }

    public function test_replacement_generation_builds_all_slots_without_writing_saved_or_planned_meals(): void
    {
        $this->fillWeek($this->owner);
        $before = $this->ownPlan();
        $draft = $this->readyReplacement();
        $this->assertCount(21, $draft->proposals);
        $this->assertSame(3, $this->generationCalls);
        $this->assertSame($before, $this->ownPlan());
        $this->assertDatabaseCount('saved_meals', 1);
    }

    public function test_fill_mode_still_rejects_a_full_week(): void
    {
        $this->fillWeek($this->owner);
        $this->actingAs($this->owner)->post(route('weekly-meals.store'), ['start_date' => '2026-11-09', 'mode' => 'fill'])
            ->assertSessionHasErrors('week');
        $this->assertDatabaseCount('weekly_meal_drafts', 0);
        $this->assertDatabaseCount('meal_plan_entries', 21);
        Queue::assertNothingPushed();
    }

    public function test_repeated_replacement_start_submissions_dispatch_only_one_job(): void
    {
        $first = $this->startReplacement();
        $second = $this->startReplacement();
        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(GenerateWeeklyMealDraft::class, 1);
    }

    public function test_invalid_generation_mode_is_rejected_before_dispatch(): void
    {
        $this->actingAs($this->owner)->post(route('weekly-meals.store'), ['start_date' => '2026-11-09', 'mode' => 'delete'])
            ->assertSessionHasErrors('mode');
        Queue::assertNothingPushed();
    }

    public function test_replacement_preview_renders_proposals_instead_of_the_current_week(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $this->actingAs($this->owner)->get(route('weekly-meals.show', $draft->id))->assertOk()
            ->assertSee('Your replacement preview')->assertSee('Replace this week')
            ->assertSee('Keep current plan')->assertSee('breakfast option 1')->assertDontSee('Original saved meal');
    }

    public function test_generation_page_counts_full_meals_and_optional_snacks(): void
    {
        $this->owner->nutritionProfile()->update(['meals_per_day' => 5, 'snacks_per_day' => 1]);
        $this->actingAs($this->owner)->get(route('weekly-meals.create', ['date' => '2026-11-09', 'mode' => 'replace']))
            ->assertOk()->assertSee('5 full meals')->assertSee('42 slots across the week.');
    }

    public function test_replacement_confirmation_requires_an_explicit_acknowledgement(): void
    {
        $this->fillWeek($this->owner);
        $before = $this->ownPlan();
        $draft = $this->readyReplacement();
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id))
            ->assertSessionHasErrors('replace_confirmed');
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_confirmation_replaces_only_the_owned_week_and_keeps_saved_meals(): void
    {
        $old = $this->fillWeek($this->owner);
        $legacy = $this->entry($old, '2026-11-10', 'snack_3');
        $outside = $this->entry($old, '2026-11-16');
        $other = User::factory()->create();
        $otherEntry = $this->entry($this->saved($other), '2026-11-09');
        $draft = $this->readyReplacement();
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id), ['replace_confirmed' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('meal-plan.index', ['date' => '2026-11-09']));
        $this->assertSame('confirmed', $draft->fresh()->status);
        $this->assertDatabaseMissing('meal_plan_entries', ['id' => $legacy->id]);
        $this->assertDatabaseHas('meal_plan_entries', ['id' => $outside->id]);
        $this->assertDatabaseHas('meal_plan_entries', ['id' => $otherEntry->id]);
        $this->assertDatabaseHas('saved_meals', ['id' => $old->id]);
        $this->assertSame(21, MealPlanEntry::where('user_id', $this->owner->id)->where('plan_date', '>=', '2026-11-09')->where('plan_date', '<', '2026-11-16')->count());
    }

    public function test_repeated_confirmation_does_not_replace_the_week_twice(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $planner = app(WeeklyMealPlanner::class);
        $first = $planner->confirm($draft->id, $this->owner);
        $before = $this->ownPlan();
        $savedCount = SavedMeal::count();
        $second = $planner->confirm($draft->id, $this->owner);
        $this->assertSame(21, $first['removed']);
        $this->assertTrue($second['already_confirmed']);
        $this->assertSame($before, $this->ownPlan());
        $this->assertSame($savedCount, SavedMeal::count());
    }

    public function test_cancelled_replacement_keeps_the_original_plan(): void
    {
        $this->fillWeek($this->owner);
        $before = $this->ownPlan();
        $draft = $this->readyReplacement();
        $this->actingAs($this->owner)->post(route('weekly-meals.cancel', $draft->id))->assertRedirect();
        $this->assertSame('cancelled', $draft->fresh()->status);
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_plan_changes_during_generation_stop_before_an_ai_request(): void
    {
        $old = $this->fillWeek($this->owner);
        $draft = $this->startReplacement();
        $new = $this->entry($old, '2026-11-09', 'snack_1');
        try {
            (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
            $this->fail('Changed baseline must stop generation.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, $this->generationCalls);
            $this->assertSame('failed', $draft->fresh()->status);
            $this->assertDatabaseHas('meal_plan_entries', ['id' => $new->id]);
        }
    }

    public function test_plan_changes_after_preview_reject_confirmation_without_deleting_anything(): void
    {
        $old = $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $this->entry($old, '2026-11-09', 'snack_1');
        $before = $this->ownPlan();
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id), ['replace_confirmed' => 1])
            ->assertSessionHasErrors('week');
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_changed_profile_rejects_replacement_confirmation(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $before = $this->ownPlan();
        $this->owner->nutritionProfile()->update(['snacks_per_day' => 1]);
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id), ['replace_confirmed' => 1])
            ->assertSessionHasErrors('week');
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_incomplete_duplicate_or_outside_week_replacements_are_rejected(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $original = $draft->proposals;
        $before = $this->ownPlan();
        foreach (['missing', 'duplicate', 'outside'] as $case) {
            $rows = $original;
            if ($case === 'missing') {
                array_pop($rows);
            }
            if ($case === 'duplicate') {
                $rows[1] = $rows[0];
            }
            if ($case === 'outside') {
                $rows[0]['date'] = '2026-11-16';
            }
            $draft->update(['proposals' => $rows]);
            $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id), ['replace_confirmed' => 1])
                ->assertSessionHasErrors('week');
            $this->assertSame($before, $this->ownPlan());
        }
    }

    public function test_ineligible_meals_are_rejected_before_old_entries_are_deleted(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $before = $this->ownPlan();
        $rows = $draft->proposals;
        $rows[0]['snapshot']['ingredients'][0]['ingredient_id'] = 999999;
        $draft->update(['proposals' => $rows]);
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id), ['replace_confirmed' => 1])
            ->assertSessionHasErrors('week');
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_a_write_failure_rolls_back_the_deleted_week_and_new_saved_meals(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $before = $this->ownPlan();
        $savedCount = SavedMeal::count();
        $dispatcher = MealPlanEntry::getEventDispatcher();
        MealPlanEntry::setEventDispatcher(clone $dispatcher);
        MealPlanEntry::creating(function () {
            throw new RuntimeException('Simulated entry write failure');
        });
        try {
            app(WeeklyMealPlanner::class)->confirm($draft->id, $this->owner);
            $this->fail('Expected the simulated database write failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated entry write failure', $exception->getMessage());
        } finally {
            MealPlanEntry::setEventDispatcher($dispatcher);
        }
        $this->assertSame($before, $this->ownPlan());
        $this->assertSame($savedCount, SavedMeal::count());
        $this->assertSame('ready', $draft->fresh()->status);
    }

    public function test_another_user_cannot_confirm_or_cancel_a_replacement(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('weekly-meals.confirm', $draft->id), ['replace_confirmed' => 1])->assertNotFound();
        $this->actingAs($other)->post(route('weekly-meals.cancel', $draft->id))->assertNotFound();
        $this->assertDatabaseCount('meal_plan_entries', 21);
    }

    public function test_clear_confirmation_get_does_not_change_the_plan_or_previews(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->readyReplacement();
        $before = $this->ownPlan();
        $this->token();
        $this->assertSame($before, $this->ownPlan());
        $this->assertSame('ready', $draft->fresh()->status);
    }

    public function test_clear_removes_only_owned_week_entries_and_cancels_unconfirmed_previews(): void
    {
        $meal = $this->fillWeek($this->owner);
        $this->entry($meal, '2026-11-10', 'snack_3');
        $outside = $this->entry($meal, '2026-11-16');
        $other = User::factory()->create();
        $otherEntry = $this->entry($this->saved($other));
        $draft = $this->readyReplacement();
        $savedCount = SavedMeal::count();
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $this->token(), 'clear_confirmed' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('meal-plan.index', ['date' => '2026-11-09']));
        $this->assertSame(0, MealPlanEntry::where('user_id', $this->owner->id)->where('plan_date', '>=', '2026-11-09')->where('plan_date', '<', '2026-11-16')->count());
        $this->assertDatabaseHas('meal_plan_entries', ['id' => $outside->id]);
        $this->assertDatabaseHas('meal_plan_entries', ['id' => $otherEntry->id]);
        $this->assertSame($savedCount, SavedMeal::count());
        $this->assertSame('cancelled', $draft->fresh()->status);
    }

    public function test_clear_requires_an_explicit_acknowledgement(): void
    {
        $this->fillWeek($this->owner);
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $this->token()])
            ->assertSessionHasErrors('clear_confirmed');
        $this->assertDatabaseCount('meal_plan_entries', 21);
    }

    public function test_a_stale_clear_confirmation_does_not_delete_later_edits(): void
    {
        $meal = $this->fillWeek($this->owner);
        $token = $this->token();
        $this->entry($meal, '2026-11-09', 'snack_1');
        $before = $this->ownPlan();
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $token, 'clear_confirmed' => 1])
            ->assertSessionHasErrors('week');
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_a_clear_token_cannot_be_used_by_another_user(): void
    {
        $this->fillWeek($this->owner);
        $token = $this->token();
        $this->actingAs(User::factory()->create())->post(route('meal-plan.clear.store'), ['token' => $token, 'clear_confirmed' => 1])->assertNotFound();
        $this->assertDatabaseCount('meal_plan_entries', 21);
    }

    public function test_tampered_clear_tokens_do_not_remove_meals(): void
    {
        $this->fillWeek($this->owner);
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => 'not-an-encrypted-confirmation', 'clear_confirmed' => 1])
            ->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_plan_entries', 21);
    }

    public function test_duplicate_clear_cannot_remove_a_plan_added_after_the_first_clear(): void
    {
        $meal = $this->fillWeek($this->owner);
        $token = $this->token();
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $token, 'clear_confirmed' => 1])->assertSessionHasNoErrors();
        $new = $this->entry($meal);
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $token, 'clear_confirmed' => 1])->assertSessionHasErrors('week');
        $this->assertDatabaseHas('meal_plan_entries', ['id' => $new->id]);
    }

    public function test_clearing_a_week_cancels_a_queued_replacement_before_it_can_generate(): void
    {
        $this->fillWeek($this->owner);
        $draft = $this->startReplacement();
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $this->token(), 'clear_confirmed' => 1])->assertSessionHasNoErrors();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('cancelled', $draft->fresh()->status);
        $this->assertSame(0, $this->generationCalls);
        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_week_capture_includes_sunday_date_and_timestamp_entries_but_not_adjacent_weeks(): void
    {
        $meal = $this->saved($this->owner);
        $monday = $this->entry($meal, '2026-11-09', 'breakfast');
        $sundayDate = $this->entry($meal, '2026-11-15', 'lunch');
        $sundayLate = $this->entry($meal, '2026-11-15', 'dinner');
        DB::table('meal_plan_entries')->where('id', $sundayDate->id)->update(['plan_date' => '2026-11-15']);
        DB::table('meal_plan_entries')->where('id', $sundayLate->id)->update(['plan_date' => '2026-11-15 23:59:59']);
        $this->entry($meal, '2026-11-08', 'dinner');
        $this->entry($meal, '2026-11-16', 'breakfast');
        $other = User::factory()->create();
        $this->entry($this->saved($other), '2026-11-15', 'lunch');
        $state = app(MealPlanWeekState::class)->capture($this->owner->id, CarbonImmutable::parse('2026-11-09', 'Europe/London'));
        $this->assertSame([$monday->id, $sundayDate->id, $sundayLate->id], $state['entries']->pluck('id')->all());
        $this->assertCount(3, $state['fixed']);
    }

    public function test_sunday_edits_invalidate_an_open_clear_confirmation(): void
    {
        $this->fillWeek($this->owner);
        $token = $this->token();
        $sunday = MealPlanEntry::where('user_id', $this->owner->id)->whereDate('plan_date', '2026-11-15')->firstOrFail();
        $snapshot = $sunday->snapshot;
        $snapshot['name'] = 'Sunday meal edited after confirmation opened';
        $sunday->update(['snapshot' => $snapshot]);
        $before = $this->ownPlan();
        $this->actingAs($this->owner)->post(route('meal-plan.clear.store'), ['token' => $token, 'clear_confirmed' => 1])
            ->assertSessionHasErrors('week');
        $this->assertSame($before, $this->ownPlan());
    }

    public function test_guests_cannot_open_or_submit_clear_week(): void
    {
        $this->get(route('meal-plan.clear.create'))->assertRedirect(route('login'));
        $this->post(route('meal-plan.clear.store'), ['token' => 'anything', 'clear_confirmed' => 1])->assertRedirect(route('login'));
    }
}
