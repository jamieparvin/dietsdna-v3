<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\SavedMeal;
use App\Models\User;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\MealTargetAllocator;
use App\Services\Meals\PlannedMealSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BalancedMealIdeasTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private $ingredients;
    private int $calls = 0;
    private int $revision = 1;
    private bool $allUnfit = false;
    private bool $partial = false;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Use phpunit-meal-ideas.xml with disposable in-memory SQLite.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create(['meals_per_day' => 5, 'snacks_per_day' => 1]);
        $this->ingredients = collect();
        foreach ([[400,100,0,0],[400,0,100,0],[900,0,0,100]] as $i => [$energy,$protein,$carbs,$fat]) {
            $this->ingredients->push(Ingredient::create([
                'food_code' => 'IDEA-TEST-'.$i, 'name' => 'Synthetic ingredient '.$i,
                'category' => 'Vegetables', 'sub_category' => 'Test', 'preparation_state' => 'raw',
                'energy_kcal' => $energy, 'protein_g' => $protein, 'carbohydrate_g' => $carbs,
                'fat_g' => $fat, 'fibre_g' => 0, 'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0, 'is_active' => true,
            ]));
        }
        $this->mock(MealIdeaGenerator::class, function ($mock) {
            $mock->shouldReceive('context')->andReturnUsing(fn ($user, $type = 'lunch') => $this->context($user, $type));
            $mock->shouldReceive('generate')->andReturnUsing(function ($user, $type) {
                $this->calls++;
                return $this->raw($this->context($user, $type));
            });
        });
    }

    private function context(?User $user = null, string $type = 'breakfast'): array
    {
        $user ??= $this->owner;
        $profile = $user->nutritionProfile()->first();
        $daily = ['calories_kcal' => 3210, 'protein_g' => 240.75, 'carbohydrate_g' => 361.125, 'fat_g' => 89.1666666667];
        $allocation = app(MealTargetAllocator::class)->allocate($daily, (int) $profile->meals_per_day, (int) $profile->snacks_per_day);
        return ['signature' => 'test-'.$user->id.'-'.$this->revision.'-'.$type.'-'.$profile->meals_per_day.'-'.$profile->snacks_per_day,
            'meal_type' => $type, 'ingredients' => $this->ingredients,
            'meal_targets' => $allocation[$type]['targets'], 'targets' => $daily,
            'meal_count' => (int) $profile->meals_per_day, 'snack_count' => (int) $profile->snacks_per_day, 'allocation' => $allocation];
    }

    private function raw(array $context): array
    {
        $ideas = [];
        foreach (range(1,3) as $n) {
            $rows = $context['ingredients']->map(fn ($food, $i) => ['name' => $food->name, 'preparation_state' => $food->preparation_state, 'quantity_g' => [20,40,10][$i]])->all();
            if ($this->allUnfit || ($this->partial && $n === 1)) { array_pop($rows); }
            $ideas[] = ['name' => 'Meal alternative '.$n, 'description' => 'A synthetic test option.',
                'instructions' => 'Prepare the listed ingredients.', 'ingredients' => $rows, 'totals' => [], 'calorie_difference' => -9999];
        }
        return ['signature' => $context['signature'], 'created_at' => now()->timestamp, 'meal_type' => $context['meal_type'], 'ideas' => $ideas];
    }

    private function batch(string $type = 'breakfast'): array
    {
        $this->actingAs($this->owner)->post(route('meal-ideas.store'), ['meal_type' => $type])->assertSessionHasNoErrors()->assertRedirect();
        return session('meal_ideas.user_'.$this->owner->id);
    }

    private function payload(array $batch, int $index = 0): array
    {
        return ['idea_index' => $index, 'batch_token' => hash('sha256', json_encode($batch, JSON_THROW_ON_ERROR))];
    }

    public function test_generation_stores_three_individually_portioned_options_without_saving_meals(): void
    {
        $batch = $this->batch();
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame(1, $this->calls);
        $this->assertSame(MealIdeaPortionBalancer::VERSION, $batch['portion_version']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertEqualsWithDelta(577.8, $idea['totals']['energy_kcal'], 28.89);
            $this->assertEqualsWithDelta(43.335, $idea['totals']['protein_g'], 4.3335);
        }
        $this->assertDatabaseCount('saved_meals', 0);
        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_page_shows_full_meals_optional_snacks_and_available_carbohydrate(): void
    {
        $this->batch();
        $this->actingAs($this->owner)->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))
            ->assertOk()->assertSee('5 full meals')->assertSee('6 eating occasions in total')
            ->assertSee('optional snack')->assertSee('Estimated available carbs:')->assertSee('How portions were checked');
        $this->assertSame(1, $this->calls);
    }

    public function test_refresh_does_not_generate_or_resize_the_batch(): void
    {
        $batch = $this->batch();
        $this->actingAs($this->owner)->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))->assertOk();
        $this->assertSame($batch, session('meal_ideas.user_'.$this->owner->id));
        $this->assertSame(1, $this->calls);
    }

    public function test_saving_keeps_exact_displayed_quantities_and_nutrition(): void
    {
        $batch = $this->batch();
        $expected = app(PlannedMealSnapshot::class)->fromIdea($batch['ideas'][0], $this->ingredients);
        $this->actingAs($this->owner)->post(route('saved-meals.store'), $this->payload($batch))
            ->assertSessionHasNoErrors()->assertRedirect(route('meal-ideas.index', ['meal_type' => 'breakfast']));
        $meal = SavedMeal::firstOrFail();
        $this->assertEquals($expected, $meal->snapshot);
        $this->assertSame($batch, session('meal_ideas.user_'.$this->owner->id));
        $this->assertArrayNotHasKey('calorie_difference', $meal->snapshot);
        $this->assertSame(1, $this->calls);
    }

    public function test_saved_indicator_uses_the_same_snapshot_hash_as_saving(): void
    {
        $batch = $this->batch();
        $this->actingAs($this->owner)->post(route('saved-meals.store'), $this->payload($batch))->assertSessionHasNoErrors();
        $response = $this->actingAs($this->owner)->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))->assertOk()->assertSee('Saved ✓');
        $this->assertSame([0], $response->viewData('savedIdeaIndexes'));
    }

    public function test_repeated_save_is_idempotent_and_another_option_can_still_be_saved(): void
    {
        $batch = $this->batch();
        foreach ([0,0,1] as $index) {
            $this->actingAs($this->owner)->post(route('saved-meals.store'), $this->payload($batch, $index))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('saved_meals', 2);
        $response = $this->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))->assertOk();
        $this->assertSame([0,1], $response->viewData('savedIdeaIndexes'));
    }

    public function test_old_unchecked_batches_are_hidden_without_changing_saved_meals(): void
    {
        $old = $this->raw($this->context());
        $snapshot = ['name' => 'Existing saved meal', 'ingredients' => [], 'totals' => []];
        SavedMeal::create(['user_id' => $this->owner->id, 'content_hash' => hash('sha256', 'old'), 'meal_type' => 'breakfast', 'snapshot' => $snapshot]);
        $this->actingAs($this->owner)->withSession(['meal_ideas.user_'.$this->owner->id => $old])
            ->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))->assertOk()->assertSee('new portion check')->assertDontSee('Meal alternative 1');
        $this->assertNull(session('meal_ideas.user_'.$this->owner->id));
        $this->assertEquals($snapshot, SavedMeal::firstOrFail()->snapshot);
        $this->assertSame(0, $this->calls);
    }

    public function test_stale_forms_cannot_save_an_old_unchecked_batch(): void
    {
        $old = $this->raw($this->context());
        $this->actingAs($this->owner)->withSession(['meal_ideas.user_'.$this->owner->id => $old])
            ->post(route('saved-meals.store'), $this->payload($old))->assertSessionHasErrors('meal');
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_changed_context_rejects_saving_without_mutating_saved_meals(): void
    {
        $batch = $this->batch();
        $this->revision++;
        $this->actingAs($this->owner)->post(route('saved-meals.store'), $this->payload($batch))->assertSessionHasErrors('meal');
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_wrong_batch_token_and_expired_batch_are_rejected(): void
    {
        $batch = $this->batch();
        $this->actingAs($this->owner)->post(route('saved-meals.store'), ['idea_index' => 0, 'batch_token' => str_repeat('0', 64)])->assertSessionHasErrors('meal');
        $batch['created_at'] = now()->subMinutes(31)->timestamp;
        $this->withSession(['meal_ideas.user_'.$this->owner->id => $batch])->post(route('saved-meals.store'), $this->payload($batch))->assertSessionHasErrors('meal');
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_client_food_quantities_are_ignored_and_displayed_session_portions_are_saved(): void
    {
        $batch = $this->batch();
        $payload = $this->payload($batch) + ['user_id' => 9999, 'ingredients' => [['quantity_g' => 9999]], 'totals' => ['energy_kcal' => 1]];
        $this->actingAs($this->owner)->post(route('saved-meals.store'), $payload)->assertSessionHasNoErrors();
        $meal = SavedMeal::firstOrFail();
        $this->assertSame($this->owner->id, $meal->user_id);
        $this->assertEquals($batch['ideas'][0]['ingredients'], $meal->snapshot['ingredients']);
    }

    public function test_another_user_cannot_save_the_owners_session_batch(): void
    {
        $batch = $this->batch();
        $other = User::factory()->create();
        $other->nutritionProfile()->create(['meals_per_day' => 5, 'snacks_per_day' => 1]);
        $this->actingAs($other)->post(route('saved-meals.store'), $this->payload($batch))->assertSessionHasErrors('meal');
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_partial_results_are_explained_without_a_second_ai_call(): void
    {
        $this->partial = true;
        $batch = $this->batch();
        $this->assertCount(2, $batch['ideas']);
        $this->assertSame('Meal alternative 2', $batch['ideas'][0]['name']);
        $this->actingAs($this->owner)->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))->assertOk()->assertSee('Showing 2 of 3 generated ideas.');
        $this->assertSame(1, $this->calls);
        $this->actingAs($this->owner)->post(route('saved-meals.store'), $this->payload($batch, 2))->assertSessionHasErrors('meal');
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_failed_new_batch_retains_the_previous_valid_batch_and_does_not_retry(): void
    {
        $old = $this->batch();
        $this->allUnfit = true;
        $this->actingAs($this->owner)->post(route('meal-ideas.store'), ['meal_type' => 'breakfast'])->assertSessionHasErrors('generation');
        $this->assertSame($old, session('meal_ideas.user_'.$this->owner->id));
        $this->assertSame(2, $this->calls);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_snacks_use_one_snack_budget_and_zero_snacks_are_named_correctly(): void
    {
        $batch = $this->batch('snack');
        foreach ($batch['ideas'] as $idea) { $this->assertEqualsWithDelta(321, $idea['totals']['energy_kcal'], 16.05); }
        $this->owner->nutritionProfile()->update(['snacks_per_day' => 0]);
        $this->actingAs($this->owner)->get(route('meal-ideas.index', ['meal_type' => 'breakfast']))->assertOk()->assertSee('no optional snacks')->assertSee('5 eating occasions in total');
    }

    public function test_guests_cannot_generate_or_save_ideas(): void
    {
        $this->post(route('meal-ideas.store'), ['meal_type' => 'breakfast'])->assertRedirect(route('login'));
        $this->post(route('saved-meals.store'), ['idea_index' => 0, 'batch_token' => str_repeat('0',64)])->assertRedirect(route('login'));
    }
    public function test_real_generation_brief_requires_a_suitable_fat_source_only_when_needed(): void
    {
        $this->owner->nutritionProfile()->update([
            'goal' => 'build_muscle', 'height_cm' => 180, 'weight_kg' => 80,
            'date_of_birth' => '1996-06-01', 'activity_level' => 'moderate',
            'calculation_sex' => 'male', 'planning_context' => 'general_adult',
            'resistance_training' => true, 'dietary_preference' => 'no_preference',
            'cooking_minutes' => 30, 'macro_mode' => 'goal', 'allergies' => null, 'foods_to_avoid' => null,
        ]);
        \Illuminate\Support\Facades\DB::table('nutrition_calculator_settings')->updateOrInsert(['id' => 1], [
            'parameters' => json_encode(\App\Services\Nutrition\NutritionCalculatorSettings::defaults(), JSON_THROW_ON_ERROR),
            'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->mock(\App\Services\Recipes\RecipeGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->withArgs(function ($catalog, $count, $brief) {
                $this->assertSame(3, $count);
                $this->assertStringContainsString('include a suitable fat source', $brief);
                $this->assertStringContainsString('do not automatically add more', $brief);
                $this->assertStringContainsString("excluded by the user's choices", $brief);
                $this->assertStringContainsString('actually', $brief);
                return true;
            })->andReturn(array_map(fn ($n) => [
                'name' => 'Catalog option '.$n, 'description' => 'Test catalog option.',
                'instructions' => 'Prepare the listed ingredients.', 'servings' => 1,
                'ingredients' => $this->ingredients->map(fn ($food, $i) => ['ingredient_id' => $food->id, 'quantity_g' => [30,60,10][$i]])->all(),
            ], [1,2,3]));
        });
        $this->app->forgetInstance(MealIdeaGenerator::class);
        $batch = app(MealIdeaGenerator::class)->generate($this->owner, 'breakfast');
        $this->assertCount(3, $batch['ideas']);
    }

}
