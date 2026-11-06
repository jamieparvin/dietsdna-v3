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
use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\WeeklyMealPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class WeeklyMealPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $generationCalls = 0;

    private $ingredients;

    // Refuse migrations unless this suite is using disposable in-memory SQLite.
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Run this suite with phpunit-weekly.xml. A real database must not be refreshed.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // This suite exercises meal behavior with minimal profiles; onboarding has its own route coverage.
        $this->withoutMiddleware(EnsureSetupComplete::class);
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create(['meals_per_day' => 3, 'snacks_per_day' => 2]);
        $this->ingredients = collect();

        foreach (['Broccoli', 'Carrots'] as $index => $name) {
            $this->ingredients->push(Ingredient::query()->create([
                'food_code' => 'WEEK-TEST-'.$index,
                'name' => $name,
                'category' => 'Vegetables',
                'sub_category' => 'Vegetables',
                'preparation_state' => 'raw',
                'energy_kcal' => 100, 'protein_g' => 10,
                'carbohydrate_g' => 10, 'sugars_g' => 1,
                'fat_g' => 2, 'saturates_g' => 1,
                'fibre_g' => 2, 'salt_g' => 0.01, 'is_active' => true,
            ]));
        }

        $this->mock(MealIdeaGenerator::class, function ($mock) {
            $mock->shouldReceive('context')->andReturnUsing(fn ($user, $type = 'lunch') => [
                'signature' => 'test-catalog', 'ingredients' => $this->ingredients,
                'meal_count' => (int) $user->nutritionProfile()->first()->meals_per_day,
                'snack_count' => (int) $user->nutritionProfile()->first()->snacks_per_day,
                'meal_type' => $type,
                'targets' => ['calories_kcal' => 500, 'protein_g' => 50, 'carbohydrate_g' => 40, 'fat_g' => 10],
            ]);
            $mock->shouldReceive('generate')->andReturnUsing(function ($user, $type) {
                $this->generationCalls++;

                return ['ideas' => [$this->idea($type.'-1'), $this->idea($type.'-2'), $this->idea($type.'-3')]];
            });
        });
    }

    private function idea(string $name): array
    {
        return [
            'name' => $name, 'description' => 'A test meal.', 'instructions' => 'Prepare the listed ingredients.',
            'ingredients' => $this->ingredients->map(fn ($ingredient) => [
                'name' => $ingredient->name, 'preparation_state' => 'raw', 'quantity_g' => 50.0,
            ])->all(),
            'totals' => ['energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10, 'fat_g' => 2, 'sugars_g' => 1, 'saturates_g' => 1, 'fibre_g' => 2, 'salt_g' => 0.01],
            'calorie_difference' => -50,
        ];
    }

    private function saved(User $user, string $name): SavedMeal
    {
        $helper = app(PlannedMealSnapshot::class);
        $snapshot = $helper->fromIdea($this->idea($name), $this->ingredients);

        return SavedMeal::query()->create(['user_id' => $user->id, 'content_hash' => $helper->hash($snapshot), 'meal_type' => 'lunch', 'snapshot' => $snapshot]);
    }

    private function entry(SavedMeal $meal, string $slot = 'lunch'): MealPlanEntry
    {
        return MealPlanEntry::query()->create([
            'user_id' => $meal->user_id, 'saved_meal_id' => $meal->id,
            'plan_date' => '2026-10-26', 'slot' => $slot, 'snapshot' => $meal->snapshot,
        ]);
    }

    private function draft(): WeeklyMealDraft
    {
        $source = app(WeeklyMealPlanner::class)->source($this->owner);

        return WeeklyMealDraft::query()->create([
            'user_id' => $this->owner->id, 'start_date' => '2026-10-26', 'status' => 'queued',
            'source_signature' => $source['signature'], 'meal_count' => 5, 'slots' => $source['slots'],
        ]);
    }

    public function test_guests_cannot_generate_a_week(): void
    {
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-10-26'])->assertRedirect(route('login'));
    }

    public function test_repeated_start_submissions_dispatch_one_background_job(): void
    {
        Queue::fake();
        $this->actingAs($this->owner);
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-10-26'])->assertRedirect();
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-10-26'])->assertRedirect();
        $this->assertDatabaseCount('weekly_meal_drafts', 1);
        Queue::assertPushed(GenerateWeeklyMealDraft::class, 1);
    }

    public function test_generation_builds_a_preview_without_writing_the_plan_or_saved_meals(): void
    {
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertCount(35, $draft->proposals);
        $this->assertSame(4, $draft->completed_types);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_five_full_meals_generate_thirty_five_main_meals_without_snacks(): void
    {
        $this->owner->nutritionProfile()->update(['meals_per_day' => 5, 'snacks_per_day' => 0]);
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertCount(35, $draft->proposals);
        $this->assertSame(3, $draft->completed_types);
        $this->assertArrayHasKey('meal_2', $draft->slots);
        $this->assertArrayHasKey('meal_4', $draft->slots);
        $this->assertNotContains('snack', array_column($draft->proposals, 'meal_type'));
    }

    public function test_six_full_meals_and_two_optional_snacks_generate_fifty_six_slots(): void
    {
        $this->owner->nutritionProfile()->update(['meals_per_day' => 6, 'snacks_per_day' => 2]);
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertCount(56, $draft->proposals);
        $this->assertSame(4, $draft->completed_types);
        $this->assertCount(14, array_filter($draft->proposals, fn ($row) => $row['meal_type'] === 'snack'));
    }

    public function test_snack_setting_saves_for_the_authenticated_user_only(): void
    {
        $other = User::factory()->create();
        $other->nutritionProfile()->create(['meals_per_day' => 5, 'snacks_per_day' => 0]);
        $this->actingAs($this->owner)->put(route('nutrition-profile.update'), ['snacks_per_day' => 1])
            ->assertRedirect(route('setup'));
        $this->assertSame(1, $this->owner->nutritionProfile()->first()->snacks_per_day);
        $this->assertSame(0, $other->nutritionProfile()->first()->snacks_per_day);
    }

    public function test_invalid_snack_setting_is_rejected_without_changing_the_profile(): void
    {
        $this->actingAs($this->owner)->put(route('nutrition-profile.update'), ['snacks_per_day' => 3])
            ->assertSessionHasErrors('snacks_per_day');
        $this->assertSame(2, $this->owner->nutritionProfile()->first()->snacks_per_day);
    }

    public function test_changing_optional_snacks_invalidates_an_existing_preview(): void
    {
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->owner->nutritionProfile()->update(['snacks_per_day' => 0]);
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id))->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_confirmation_preserves_existing_slots_and_is_idempotent(): void
    {
        $old = $this->saved($this->owner, 'Existing breakfast');
        $existing = $this->entry($old, 'breakfast');
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertCount(34, $draft->fresh()->proposals);
        $planner = app(WeeklyMealPlanner::class);
        $result = $planner->confirm($draft->id, $this->owner);
        $this->assertSame(34, $result['added']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseCount('meal_plan_entries', 35);
        $this->assertEquals($old->snapshot, $existing->fresh()->snapshot);
        $this->assertTrue($planner->confirm($draft->id, $this->owner)['already_confirmed']);
        $this->assertDatabaseCount('meal_plan_entries', 35);
    }

    private function failedStoredDraft(): WeeklyMealDraft
    {
        $draft = $this->draft();
        $helper = app(PlannedMealSnapshot::class);
        $pools = [];
        foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $type) {
            foreach (range(1, 3) as $number) {
                $idea = $this->idea($type.'-'.$number);
                $idea['instructions'] = 'Cook 80g of the ingredients for 5 minutes.';
                $pools[$type][] = $helper->fromIdea($idea, $this->ingredients);
            }
        }
        $draft->update(['status' => 'failed', 'generated' => $pools, 'error' => 'Old amount error']);

        return $draft->fresh();
    }

    public function test_failed_stored_options_can_be_rebalanced_without_ai_or_plan_writes(): void
    {
        $draft = $this->failedStoredDraft();
        app(WeeklyMealPlanner::class)->rebalanceStored($draft);
        $this->assertSame(0, $this->generationCalls);
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertNull($draft->error);
        $this->assertCount(35, $draft->proposals);
        $this->assertStringContainsString('the listed amount of', $draft->proposals[0]['snapshot']['instructions']);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_legacy_ready_preview_can_be_rebuilt_without_ai_or_plan_writes(): void
    {
        $draft = $this->failedStoredDraft();
        $source = app(WeeklyMealPlanner::class)->source($this->owner);
        $draft->update(['status' => 'ready', 'source_signature' => $source['legacySignature']]);
        app(WeeklyMealPlanner::class)->rebalanceStored($draft);
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertSame($source['signature'], $draft->fresh()->source_signature);
        $this->assertSame(0, $this->generationCalls);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_legacy_recovery_does_not_bypass_changed_profile_checks(): void
    {
        $draft = $this->failedStoredDraft();
        $source = app(WeeklyMealPlanner::class)->source($this->owner);
        $draft->update(['source_signature' => $source['legacySignature']]);
        $this->owner->nutritionProfile()->update(['snacks_per_day' => 0]);
        try {
            app(WeeklyMealPlanner::class)->rebalanceStored($draft);
            $this->fail('Changed profiles cannot migrate a legacy preview.');
        } catch (ValidationException $exception) {
            $this->assertSame('failed', $draft->fresh()->status);
            $this->assertSame($source['legacySignature'], $draft->fresh()->source_signature);
            $this->assertSame(0, $this->generationCalls);
        }
    }

    public function test_recovery_rejects_a_changed_profile_without_ai(): void
    {
        $draft = $this->failedStoredDraft();
        $this->owner->nutritionProfile()->update(['snacks_per_day' => 0]);
        $this->expectException(ValidationException::class);
        try {
            app(WeeklyMealPlanner::class)->rebalanceStored($draft);
        } finally {
            $this->assertSame(0, $this->generationCalls);
        }
    }

    public function test_recovery_requires_complete_stored_pools_without_ai(): void
    {
        $draft = $this->failedStoredDraft();
        $draft->update(['generated' => []]);
        $this->expectException(ValidationException::class);
        try {
            app(WeeklyMealPlanner::class)->rebalanceStored($draft);
        } finally {
            $this->assertSame(0, $this->generationCalls);
        }
    }

    public function test_recovery_does_not_change_a_confirmed_preview(): void
    {
        $draft = $this->failedStoredDraft();
        $draft->update(['status' => 'confirmed']);
        try {
            app(WeeklyMealPlanner::class)->rebalanceStored($draft);
            $this->fail('Confirmed previews cannot be recovered.');
        } catch (ValidationException $exception) {
            $this->assertSame('confirmed', $draft->fresh()->status);
            $this->assertSame(0, $this->generationCalls);
        }
    }

    public function test_plan_changes_after_balancing_require_a_new_preview_and_preserve_existing_meals(): void
    {
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $later = $this->entry($this->saved($this->owner, 'Added while previewing'), 'lunch');
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id))->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_plan_entries', 1);
        $this->assertDatabaseCount('saved_meals', 1);
        $this->assertNotNull($later->fresh());
    }

    public function test_every_generated_day_passes_the_daily_balance_check_after_rounding(): void
    {
        $this->owner->nutritionProfile()->update(['meals_per_day' => 6, 'snacks_per_day' => 2]);
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        app(MealPortionBalancer::class)->assertFits($draft->proposals, [], [
            'calories_kcal' => 500, 'protein_g' => 50, 'carbohydrate_g' => 40, 'fat_g' => 10,
        ]);
        foreach ($draft->proposals as $proposal) {
            $this->assertSame(MealPortionBalancer::VERSION, $proposal['snapshot']['portion_balance']['version']);
        }
    }

    public function test_changed_profile_rejects_confirmation_without_writing_any_meals(): void
    {
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->owner->nutritionProfile()->update(['meals_per_day' => 6]);
        $this->actingAs($this->owner)->post(route('weekly-meals.confirm', $draft->id))->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_another_user_cannot_read_or_confirm_a_preview(): void
    {
        $draft = $this->draft();
        $this->actingAs(User::factory()->create());
        $this->get(route('weekly-meals.show', $draft->id))->assertNotFound();
        $this->get(route('weekly-meals.status', $draft->id))->assertNotFound();
        $this->post(route('weekly-meals.confirm', $draft->id))->assertNotFound();
    }

    public function test_ready_preview_and_swap_picker_render_for_the_owner(): void
    {
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->actingAs($this->owner)->get(route('weekly-meals.show', $draft->id))
            ->assertOk()->assertSee('Your weekly preview')->assertSee('Snack 2');
        $planned = $this->entry($this->saved($this->owner, 'Original'));
        $this->saved($this->owner, 'Swap option');
        $this->get(route('meal-plan.swap', $planned->id))->assertOk()->assertSee('Use this meal instead');
    }

    public function test_an_ineligible_replacement_does_not_change_the_plan(): void
    {
        $planned = $this->entry($this->saved($this->owner, 'Original'));
        $replacement = $this->saved($this->owner, 'Replacement');
        $snapshot = $replacement->snapshot;
        $snapshot['ingredients'][0]['ingredient_id'] = 999999;
        $replacement->update(['snapshot' => $snapshot]);
        $originalId = $planned->saved_meal_id;
        $this->actingAs($this->owner)->put(route('meal-plan.swap.update', $planned->id), [
            'saved_meal_id' => $replacement->id,
            'entry_token' => app(PlannedMealSnapshot::class)->entryToken($planned),
        ])->assertSessionHasErrors('meal');
        $this->assertSame($originalId, $planned->fresh()->saved_meal_id);
    }

    public function test_swap_changes_one_entry_without_changing_saved_portions_or_other_slots(): void
    {
        $original = $this->saved($this->owner, 'Original');
        $planned = $this->entry($original);
        $other = $this->entry($original, 'dinner');
        $replacement = $this->saved($this->owner, 'Replacement');
        $token = app(PlannedMealSnapshot::class)->entryToken($planned);
        $this->actingAs($this->owner)->put(route('meal-plan.swap.update', $planned->id), [
            'saved_meal_id' => $replacement->id, 'entry_token' => $token,
        ])->assertRedirect(route('meal-plan.index', ['date' => '2026-10-26']));
        $this->assertEquals($replacement->snapshot, $planned->fresh()->snapshot);
        $this->assertSame($original->id, $other->fresh()->saved_meal_id);
        $this->assertDatabaseCount('saved_meals', 2);
        $this->put(route('meal-plan.swap.update', $planned->id), [
            'saved_meal_id' => $original->id, 'entry_token' => $token,
        ])->assertSessionHasErrors('meal');
        $this->assertSame($replacement->id, $planned->fresh()->saved_meal_id);
    }

    public function test_other_users_entries_and_saved_meals_cannot_be_used_for_swaps(): void
    {
        $original = $this->saved($this->owner, 'Original');
        $planned = $this->entry($original);
        $stranger = User::factory()->create();
        $foreignMeal = $this->saved($stranger, 'Private meal');
        $this->actingAs($stranger)->get(route('meal-plan.swap', $planned->id))->assertNotFound();
        $this->actingAs($this->owner)->put(route('meal-plan.swap.update', $planned->id), [
            'saved_meal_id' => $foreignMeal->id,
            'entry_token' => app(PlannedMealSnapshot::class)->entryToken($planned),
        ])->assertNotFound();
        $this->assertSame($original->id, $planned->fresh()->saved_meal_id);
    }
}
