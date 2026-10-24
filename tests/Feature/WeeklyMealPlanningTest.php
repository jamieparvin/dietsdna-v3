<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
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
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create(['meals_per_day' => 5]);
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
                'meal_type' => $type,
            ]);
            $mock->shouldReceive('generate')->andReturnUsing(fn ($user, $type) => [
                'ideas' => [$this->idea($type.'-1'), $this->idea($type.'-2'), $this->idea($type.'-3')],
            ]);
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

    public function test_confirmation_preserves_existing_and_newly_occupied_slots_and_is_idempotent(): void
    {
        $old = $this->saved($this->owner, 'Existing breakfast');
        $existing = $this->entry($old, 'breakfast');
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertCount(34, $draft->fresh()->proposals);
        $later = $this->entry($this->saved($this->owner, 'Added while previewing'), 'lunch');
        $planner = app(WeeklyMealPlanner::class);
        $result = $planner->confirm($draft->id, $this->owner);
        $this->assertSame(33, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('meal_plan_entries', 35);
        $this->assertEquals($old->snapshot, $existing->fresh()->snapshot);
        $this->assertSame($later->saved_meal_id, $later->fresh()->saved_meal_id);
        $this->assertTrue($planner->confirm($draft->id, $this->owner)['already_confirmed']);
        $this->assertDatabaseCount('meal_plan_entries', 35);
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
