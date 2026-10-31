<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\WeeklyMealPlanner;
use App\Services\Nutrition\FoodPreferenceGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MealGenerationExclusionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ingredient $fish;

    private Ingredient $rice;

    private Ingredient $oil;

    private Ingredient $chicken;

    private Ingredient $cooked;

    private Ingredient $beef;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.openai.key' => 'fake-key', 'services.openai.recipe_model' => 'fake-model']);
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create([
            'date_of_birth' => '1996-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ]);
        // Synthetic per-100 g sources make real optimization failures unambiguous.
        $this->fish = $this->food('Fish', 290, 50, 0, 10);
        $this->rice = $this->food('Rice', 400, 0, 100, 0, 'dry');
        $this->oil = $this->food('Olive oil', 900, 0, 0, 100, 'ready_to_eat');
        $this->chicken = $this->food('Chicken breast, raw', 290, 50, 0, 10);
        $this->cooked = $this->food('Chicken breast, cooked', 290, 50, 0, 10, 'roasted');
        $this->cooked->update(['is_active' => false]);
        app(FoodPreferenceGroups::class)->link($this->cooked, $this->chicken);
        $this->beef = $this->food('Beef', 290, 50, 0, 10);
    }

    private function food(string $name, float $energy, float $protein, float $carbs, float $fat, string $form = 'raw'): Ingredient
    {
        return Ingredient::create(['food_code' => $name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test',
            'preparation_state' => $form, 'energy_kcal' => $energy, 'protein_g' => $protein, 'carbohydrate_g' => $carbs,
            'fat_g' => $fat, 'fibre_g' => 0, 'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0, 'is_active' => true]);
    }

    private function dislikeMeat(): void
    {
        $this->actingAs($this->owner)->put(route('food-preferences.update'), ['preferences' => [
            $this->cooked->fresh()->preferenceFoodKey() => 'excluded',
            $this->beef->preferenceFoodKey() => 'excluded',
        ]])->assertSessionHasNoErrors();
    }

    private function recipes(string $type, string $prefix = 'Fish option', ?Ingredient $protein = null): array
    {
        $targets = app(MealIdeaGenerator::class)->context($this->owner, $type)['meal_targets'];

        return array_map(fn ($n) => ['name' => $prefix.' '.$n, 'description' => 'Synthetic test meal.', 'servings' => 1,
            'instructions' => ['Combine the listed ingredients.', 'Layer the listed ingredients.', 'Arrange the listed ingredients in a bowl.'][$n - 1],
            'ingredients' => [
                ['ingredient_id' => ($protein ?? $this->fish)->id, 'quantity_g' => round($targets['protein_g'] * 2, 2)],
                ['ingredient_id' => $this->rice->id, 'quantity_g' => round($targets['carbohydrate_g'], 2)],
                ['ingredient_id' => $this->oil->id, 'quantity_g' => round($targets['fat_g'] - $targets['protein_g'] * 0.2, 2)],
            ]], [1, 2, 3]);
    }

    private function response(array $recipes): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message',
            'content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => $recipes])]]]]];
    }

    private function fakeWeekly(): void
    {
        Http::fake(['api.openai.com/*' => function ($request) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);

            return Http::response($this->response($this->recipes($brief['meal_type'])));
        }]);
    }

    private function draft(): WeeklyMealDraft
    {
        $source = app(WeeklyMealPlanner::class)->source($this->owner);

        return WeeklyMealDraft::create(['user_id' => $this->owner->id, 'start_date' => '2026-11-02', 'status' => 'queued',
            'source_signature' => $source['signature'], 'meal_count' => 3, 'slots' => $source['slots'], 'mode' => 'fill']);
    }

    public function test_replacement_uses_three_candidates_and_feedback_to_complete_an_eligible_fish_batch(): void
    {
        $this->dislikeMeat();
        $first = $this->recipes('lunch');
        array_pop($first[2]['ingredients']); // No fat source: cannot meet the unchanged fat guide.
        $next = $this->recipes('lunch', 'Replacement');
        array_pop($next[0]['ingredients']); // One last-round failure must not leave the slot empty.
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->response($first))->push($this->response($next))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $batch = session('meal_ideas.user_'.$this->owner->id);
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame(2, $batch['generation_attempts']);
        $this->assertSame(6, $batch['candidate_count']);
        $this->assertSame(2, $batch['rejected_count']);
        $context = app(MealIdeaGenerator::class)->context($this->owner, 'lunch');
        foreach ($batch['ideas'] as $idea) {
            app(MealIdeaPortionBalancer::class)->assertSavable($idea, $context);
            $this->assertNotContains($this->chicken->id, array_column($idea['ingredients'], 'ingredient_id'));
            $this->assertNotContains($this->beef->id, array_column($idea['ingredients'], 'ingredient_id'));
        }
        Http::assertSentCount(2);
        $requests = Http::recorded();
        $input = json_decode($requests[1][0]['input'], true);
        $this->assertSame(3, $input['count']);
        $this->assertStringContainsString('replacement_feedback', $input['brief']);
        $this->assertStringContainsString('Fish option 3', $input['brief']);
        $this->assertStringContainsString('reason', $input['brief']);
        foreach ($requests as [$request]) {
            $catalog = json_decode($request['input'], true)['ingredient_catalog'];
            $this->assertSame([$this->fish->id, $this->rice->id, $this->oil->id], array_column($catalog, 'id'));
        }
    }

    public function test_shortfall_keeps_only_checked_distinct_options_and_explains_no_limits_were_relaxed(): void
    {
        $this->dislikeMeat();
        $recipes = $this->recipes('lunch');
        array_pop($recipes[1]['ingredients']);
        array_pop($recipes[2]['ingredients']);
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->response($recipes))->push($this->response($recipes))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $batch = session('meal_ideas.user_'.$this->owner->id);
        $this->assertCount(1, $batch['ideas']);
        $this->assertSame(1, $batch['duplicate_count']);
        $this->get(route('meal-ideas.index'))->assertOk()->assertSee('Showing 1 of 3 requested ideas.')
            ->assertSee('No exclusions, macro tolerances or portion limits were relaxed.');
        Http::assertSentCount(2); // GET cannot spend another request.
    }

    public function test_renamed_copies_are_not_used_to_pad_a_batch(): void
    {
        $this->dislikeMeat();
        $recipes = $this->recipes('lunch');
        foreach ($recipes as &$recipe) {
            $recipe['instructions'] = 'Combine the listed ingredients.';
        }
        unset($recipe);
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->response($recipes))->push($this->response($recipes))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $this->assertCount(1, session('meal_ideas.user_'.$this->owner->id)['ideas']);
        Http::assertSentCount(2);
    }

    public function test_weekly_job_uses_same_exclusions_and_rechecks_them_before_confirmation(): void
    {
        $this->dislikeMeat();
        $this->fakeWeekly();
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        foreach ($draft->fresh()->proposals as $proposal) {
            $this->assertSame([$this->fish->id, $this->rice->id, $this->oil->id], array_column($proposal['snapshot']['ingredients'], 'ingredient_id'));
        }
        Http::assertSentCount(3);
        // Even a proposal tampered independently of its source signature cannot sneak chicken in.
        $proposals = $draft->fresh()->proposals;
        $proposals[0]['snapshot']['ingredients'][0]['ingredient_id'] = $this->chicken->id;
        $proposals[0]['snapshot']['ingredients'][0]['food_key'] = $this->chicken->snapshotFoodKey();
        $draft->update(['proposals' => $proposals]);
        $this->post(route('weekly-meals.confirm', $draft))->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_preference_change_while_queued_stops_job_before_any_ai_request(): void
    {
        $draft = $this->draft();
        $this->dislikeMeat();
        try {
            (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
            $this->fail('Stale queued generation must fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed', $exception->getMessage());
        }
        $this->assertSame('failed', $draft->fresh()->status);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        Http::assertNothingSent();
    }

    public function test_preference_change_during_ai_request_discards_running_results(): void
    {
        $draft = $this->draft();
        $recipes = $this->recipes('breakfast');
        Http::fake(['api.openai.com/*' => function () use ($recipes) {
            $this->dislikeMeat();

            return Http::response($this->response($recipes));
        }]);
        try {
            (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
            $this->fail('Stale running generation must fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed', $exception->getMessage());
        }
        $this->assertSame('failed', $draft->fresh()->status);
        $this->assertEmpty($draft->fresh()->proposals);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        Http::assertSentCount(1);
    }

    public function test_preference_change_after_ready_preview_blocks_stale_confirmation_and_is_visible(): void
    {
        $this->fakeWeekly();
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->dislikeMeat();
        $this->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('older preview and cannot be confirmed');
        $this->post(route('weekly-meals.confirm', $draft))->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertSentCount(3);
    }

    public function test_older_chicken_preview_is_flagged_and_cannot_be_confirmed_after_linked_dislike(): void
    {
        Http::fake(['api.openai.com/*' => function ($request) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);

            return Http::response($this->response($this->recipes($brief['meal_type'], 'Old chicken', $this->chicken)));
        }]);
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $before = $draft->fresh()->proposals;
        $this->dislikeMeat();
        $this->get(route('weekly-meals.show', $draft))->assertOk()
            ->assertSee('older preview and cannot be confirmed')
            ->assertSee('Contains foods you now Dislike:')
            ->assertSee('This older suggestion cannot be added.');
        $this->post(route('weekly-meals.confirm', $draft))->assertSessionHasErrors('week');
        $this->assertSame($before, $draft->fresh()->proposals);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        Http::assertSentCount(3);
    }

    public function test_fill_preserves_and_labels_an_existing_now_disliked_linked_preparation(): void
    {
        $recipe = $this->recipes('lunch', 'Historical chicken', $this->cooked)[0];
        $rows = array_map(function ($row) {
            $food = Ingredient::findOrFail($row['ingredient_id']);

            return ['ingredient_id' => $food->id, 'food_key' => $food->snapshotFoodKey(), 'name' => $food->name,
                'preparation_state' => $food->preparation_state, 'quantity_g' => $row['quantity_g']];
        }, $recipe['ingredients']);
        $totals = array_fill_keys(['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g', 'sugars_g', 'saturates_g', 'fibre_g', 'salt_g'], 0.0);
        foreach ($rows as $row) {
            $food = Ingredient::findOrFail($row['ingredient_id']);
            foreach ($totals as $key => $value) {
                $totals[$key] += (float) $food->{$key} * $row['quantity_g'] / 100;
            }
        }
        $snapshot = ['name' => $recipe['name'], 'description' => $recipe['description'], 'instructions' => $recipe['instructions'],
            'ingredients' => $rows, 'totals' => $totals];
        $entry = MealPlanEntry::create(['user_id' => $this->owner->id, 'plan_date' => '2026-11-02', 'slot' => 'lunch', 'snapshot' => $snapshot]);
        $before = $entry->fresh()->getRawOriginal();
        $this->dislikeMeat();
        $this->fakeWeekly();
        $draft = $this->draft();
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('Contains foods you now Dislike:')
            ->assertSee('Chicken breast, cooked')->assertSee('This existing meal is preserved by Fill empty slots.');
        $this->get(route('meal-plan.index', ['date' => '2026-11-02']))->assertOk()->assertSee('Contains foods you now Dislike:');
        $this->post(route('weekly-meals.confirm', $draft))->assertSessionHasNoErrors();
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('meal_plan_entries', 21);
        Http::assertSentCount(3);
    }

    public function test_ai_cannot_return_a_disliked_chicken_id_outside_the_supplied_catalog(): void
    {
        $this->dislikeMeat();
        $recipes = $this->recipes('lunch', 'Invalid chicken', $this->chicken);
        Http::fake(['api.openai.com/*' => Http::response($this->response($recipes))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasErrors('generation');
        $this->assertNull(session('meal_ideas.user_'.$this->owner->id));
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertSentCount(1);
    }
}
