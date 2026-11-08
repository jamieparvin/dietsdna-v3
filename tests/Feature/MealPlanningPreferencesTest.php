<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaBatchGenerator;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\MealPreparationTime;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\WeeklyMealPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MealPlanningPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function member(int $meals = 2, int $snacks = 0, ?int $minutes = null): User
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create(['date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'high', 'goal' => 'build_muscle', 'planning_context' => 'general_adult',
            'resistance_training' => true, 'meals_per_day' => $meals, 'snacks_per_day' => $snacks, 'cooking_minutes' => $minutes]);

        return $user;
    }

    private function foods(): array
    {
        $result = [];
        foreach (['Protein' => [200, 50, 0, 0], 'Carb A' => [400, 0, 100, 0], 'Carb B' => [400, 0, 100, 0], 'Fat A' => [900, 0, 0, 100], 'Fat B' => [900, 0, 0, 100]] as $name => [$kcal, $protein, $carbs, $fat]) {
            $result[$name] = Ingredient::create(['food_code' => $name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                'energy_kcal' => $kcal, 'protein_g' => $protein, 'carbohydrate_g' => $carbs, 'fat_g' => $fat, 'fibre_g' => 0, 'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0]);
        }

        return $result;
    }

    private function recipes(array $foods, array $target, bool $feasible = true): array
    {
        $rows = $feasible ? [
            ['ingredient_id' => $foods['Protein']->id, 'quantity_g' => round($target['protein_g'] * 2, 2)],
            ['ingredient_id' => $foods['Carb A']->id, 'quantity_g' => round($target['carbohydrate_g'] / 2, 2)],
            ['ingredient_id' => $foods['Carb B']->id, 'quantity_g' => round($target['carbohydrate_g'] / 2, 2)],
            ['ingredient_id' => $foods['Fat A']->id, 'quantity_g' => round($target['fat_g'] / 2, 2)],
            ['ingredient_id' => $foods['Fat B']->id, 'quantity_g' => round($target['fat_g'] / 2, 2)],
        ] : [
            ['ingredient_id' => $foods['Protein']->id, 'quantity_g' => 100],
            ['ingredient_id' => $foods['Carb A']->id, 'quantity_g' => 100],
            ['ingredient_id' => $foods['Fat A']->id, 'quantity_g' => 15],
        ];

        return array_map(fn ($index) => ['name' => 'Option '.$index, 'description' => 'Synthetic independent nutrient sources.', 'servings' => 1,
            'instructions' => ['Combine the listed foods.', 'Layer the listed foods.', 'Arrange the listed foods.'][$index], 'ingredients' => $rows], [0, 1, 2]);
    }

    private function response(array $recipes): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => $recipes])]]]]];
    }

    private function draft(User $user): WeeklyMealDraft
    {
        $source = app(WeeklyMealPlanner::class)->source($user);

        return WeeklyMealDraft::create(['user_id' => $user->id, 'start_date' => '2026-11-09', 'status' => 'queued', 'source_signature' => $source['signature'], 'meal_count' => $source['context']['meal_count'], 'slots' => $source['slots'], 'mode' => 'fill']);
    }

    public static function snacks(): array
    {
        return ['no snacks' => [0], 'one snack' => [1], 'two snacks' => [2]];
    }

    #[DataProvider('snacks')]
    public function test_two_meal_week_requests_feasible_replacements_instead_of_failing_on_underpowered_candidates(int $snacks): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'Europe/London'));
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(2, $snacks);
        $foods = $this->foods();
        $calls = [];
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods, &$calls) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $type = $brief['meal_type'];
            $calls[$type] = ($calls[$type] ?? 0) + 1;
            $context = app(MealIdeaGenerator::class)->context($user, $type);

            return Http::response($this->response($this->recipes($foods, $context['meal_targets'], $type === 'snack' || $calls[$type] > 1)));
        }]);
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertCount(7 * (2 + $snacks), $draft->proposals);
        $this->assertSame($snacks === 2 ? 1 : 2, $calls['lunch']);
        $this->assertSame($snacks === 2 ? 1 : 2, $calls['dinner']);
        $this->assertArrayNotHasKey('breakfast', $calls);
        $source = app(WeeklyMealPlanner::class)->source($user);
        app(MealPortionBalancer::class)->assertFits($draft->proposals, [], $source['context']['targets']);
        $this->assertDatabaseCount('saved_meals', 0);
        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    private function fakeMeals(User $user, array $foods, int $prep = 4, int $cook = 6): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods, $prep, $cook) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $context = app(MealIdeaGenerator::class)->context($user, $brief['meal_type']);
            $recipes = $this->recipes($foods, $context['meal_targets']);
            if ($context['cooking_minutes'] !== null) {
                foreach ($recipes as &$recipe) {
                    $recipe['estimated_prep_minutes'] = $prep;
                    $recipe['estimated_cook_minutes'] = $cook;
                }
                unset($recipe);
            }

            return Http::response($this->response($recipes));
        }]);
    }

    public static function patterns(): array
    {
        $result = [];
        foreach (range(2, 6) as $meals) {
            foreach (range(0, 2) as $snacks) {
                $result[$meals.' meals, '.$snacks.' snacks'] = [$meals, $snacks];
            }
        }

        return $result;
    }

    #[DataProvider('patterns')]
    public function test_all_full_meal_and_snack_patterns_keep_daily_targets_portion_limits_and_estimated_time(int $meals, int $snacks): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'Europe/London'));
        $user = $this->member($meals, $snacks, 10);
        $foods = $this->foods();
        $this->fakeMeals($user, $foods);
        $source = app(WeeklyMealPlanner::class)->source($user);
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertCount(7 * ($meals + $snacks), $draft->proposals);
        $this->assertSame($source['context']['targets'], app(WeeklyMealPlanner::class)->source($user)['context']['targets']);
        app(MealPortionBalancer::class)->assertFits($draft->proposals, [], $source['context']['targets']);
        foreach ($draft->proposals as $proposal) {
            $this->assertSame(10, MealPreparationTime::total($proposal['snapshot']));
            $mass = array_sum(array_column($proposal['snapshot']['ingredients'], 'quantity_g'));
            $this->assertLessThanOrEqual($proposal['meal_type'] === 'snack' ? 500 : 1100, $mass);
            foreach ($proposal['snapshot']['ingredients'] as $row) {
                $cap = in_array($row['ingredient_id'], [$foods['Fat A']->id, $foods['Fat B']->id], true) ? 25 : ($row['ingredient_id'] === $foods['Protein']->id ? 400 : 150);
                $this->assertLessThanOrEqual($cap, $row['quantity_g']);
            }
        }
        $this->assertDatabaseCount('saved_meals', 0);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->actingAs($user)->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('Estimated total time: 10 minutes')->assertSee('AI estimate; actual time may vary');
    }

    public function test_time_preference_requests_honest_combined_prep_and_cook_estimates_with_nullable_schema_fields(): void
    {
        $user = $this->member(3, 0, 15);
        $foods = $this->foods();
        $this->fakeMeals($user, $foods, 5, 10);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $this->assertCount(3, $batch['ideas']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertSame(15, MealPreparationTime::total($idea));
        }
        Http::assertSent(function ($request) {
            $schema = $request['text']['format']['schema']['properties']['recipes']['items'];
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);

            return in_array('estimated_prep_minutes', $schema['required'], true)
                && in_array('estimated_cook_minutes', $schema['required'], true)
                && in_array('null', $schema['properties']['estimated_prep_minutes']['type'], true)
                && $brief['time_available_per_meal_minutes'] === 15
                && str_contains($request['instructions'], 'preparation and cooking');
        });
        Http::assertSentCount(1);
    }

    public function test_single_ideas_keep_honest_over_preference_estimates_without_a_paid_retry(): void
    {
        $user = $this->member(3, 0, 10);
        $this->fakeMeals($user, $this->foods(), 8, 8);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $this->assertCount(3, $batch['ideas']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertSame(16, MealPreparationTime::total($idea));
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_weekly_over_time_fallback_finishes_ready_without_touching_existing_meals(): void
    {
        $user = $this->member(2, 0, 10);
        $this->fakeMeals($user, $this->foods(), 8, 8);
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => hash('sha256', 'old-saved'), 'snapshot' => ['name' => 'Existing saved meal']]);
        $entry = MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id, 'plan_date' => '2026-10-01', 'slot' => 'lunch', 'snapshot' => $saved->snapshot]);
        $before = $entry->fresh()->getAttributes();
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        foreach ($draft->fresh()->proposals as $row) {
            $this->assertSame(16, MealPreparationTime::total($row['snapshot']));
        }
        $this->actingAs($user)->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('This meal may take longer than your preferred time.');
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('saved_meals', 1);
        Http::assertSentCount(2);
    }

    public function test_infeasible_two_meal_profile_keeps_exclusions_targets_and_existing_plan_and_fails_clearly(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member();
        $foods = $this->foods();
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $foods['Fat B']->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $source = app(WeeklyMealPlanner::class)->source($user);
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($foods, $user) {
            $input = json_decode($request['input'], true);
            $this->assertNotContains($foods['Fat B']->id, array_column($input['ingredient_catalog'], 'id'));
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);

            return Http::response($this->response($this->recipes($foods, app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets'], false)));
        }]);
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => hash('sha256', 'unchanged-before-infeasible-week'), 'snapshot' => ['name' => 'Saved historical meal']]);
        $entry = MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id, 'plan_date' => '2026-10-01', 'slot' => 'lunch', 'snapshot' => $saved->snapshot]);
        $before = $entry->fresh()->getAttributes();
        $draft = $this->draft($user);
        try {
            (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
            $this->fail('The sole eligible fat food cannot meet the daily fat target within its cap.');
        } catch (ValidationException) {
            $this->assertSame('failed', $draft->fresh()->status);
            $this->assertStringContainsString('2 full meals per day within the portion limits', $draft->fresh()->error);
            $this->assertStringContainsString('Try a fresh preview', $draft->fresh()->error);
        }
        $this->assertSame($source['context']['targets'], app(WeeklyMealPlanner::class)->source($user)['context']['targets']);
        $this->assertDatabaseCount('saved_meals', 1);
        $this->assertDatabaseCount('meal_plan_entries', 1);
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->actingAs($user)->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('Try a new weekly preview');
        $this->getJson(route('weekly-meals.status', $draft))->assertOk()->assertJsonPath('status', 'failed');
        Http::assertSentCount(4);
    }

    public static function timeOptions(): array
    {
        return ['10 minutes' => [10], '15 minutes' => [15], '20 minutes' => [20], '30 minutes' => [30], 'unspecified' => [null]];
    }

    #[DataProvider('timeOptions')]
    public function test_new_time_options_are_optional_and_save_without_changing_other_profile_fields(?int $minutes): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['cooking_minutes' => $minutes])->assertSessionHasNoErrors();
        $this->assertSame($minutes, $user->nutritionProfile()->first()->cooking_minutes);
        $this->assertSame(2, $user->nutritionProfile()->first()->meals_per_day);
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
    }

    public static function legacyTimes(): array
    {
        return ['45 minutes' => [45], '60 minutes' => [60]];
    }

    #[DataProvider('legacyTimes')]
    public function test_legacy_saved_time_is_explicit_kept_for_unrelated_saves_and_not_offered_to_new_profiles(int $minutes): void
    {
        $user = $this->member(3, 0, $minutes);
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Time available per meal')->assertSee('(previously saved)')->assertSee('Your previously saved '.$minutes.' minutes remains in use');
        $this->put(route('nutrition-profile.update'), ['height_cm' => 181, 'cooking_minutes' => $minutes])->assertSessionHasNoErrors();
        $this->assertSame($minutes, $user->nutritionProfile()->first()->cooking_minutes);
        $this->put(route('nutrition-profile.update'), ['height_cm' => 182])->assertSessionHasNoErrors();
        $this->assertSame($minutes, $user->nutritionProfile()->first()->cooking_minutes);
        $this->put(route('nutrition-profile.update'), ['cooking_minutes' => 20])->assertSessionHasNoErrors();
        $this->put(route('nutrition-profile.update'), ['cooking_minutes' => $minutes])->assertSessionHasErrors('cooking_minutes');
        $this->assertSame(20, $user->nutritionProfile()->first()->cooking_minutes);
        $other = $this->member();
        $this->actingAs($other)->put(route('nutrition-profile.update'), ['cooking_minutes' => $minutes])->assertSessionHasErrors('cooking_minutes');
    }

    public function test_time_selector_has_only_new_options_and_combined_time_help_for_new_profiles(): void
    {
        $user = $this->member();
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Time available per meal')->assertSee('spend preparing and cooking each meal.');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $options = $xpath->query('//select[@name="cooking_minutes"]/option');
        $this->assertSame(['', '10', '15', '20', '30'], array_map(fn ($node) => $node->getAttribute('value'), iterator_to_array($options)));
        $this->assertSame(0, $xpath->query('//select[@name="cooking_minutes" and @required]')->length);
    }

    public function test_missing_timing_estimates_keep_eligible_ideas_and_display_time_unavailable(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 15);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->response($this->recipes($foods, app(MealIdeaGenerator::class)->context($user, 'lunch')['meal_targets'])))]);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $this->assertCount(3, $batch['ideas']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertNull(MealPreparationTime::total($idea));
        }
        $this->actingAs($user)->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $this->get(route('meal-ideas.index'))->assertOk()->assertSee('Time unavailable');
        Http::assertSentCount(2);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_timed_pools_keep_all_fallbacks_and_prefer_within_time_when_rebalanced_without_extra_ai(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets']);
            foreach ($recipes as $index => &$recipe) {
                $recipe['estimated_prep_minutes'] = 5;
                $recipe['estimated_cook_minutes'] = $index === 0 ? 5 : 20;
            }
            unset($recipe);

            return Http::response($this->response($recipes));
        }]);
        $draft = $this->draft($user);
        $planner = app(WeeklyMealPlanner::class);
        (new GenerateWeeklyMealDraft($draft->id))->handle($planner);
        $draft->refresh();
        foreach ($draft->generated as $pool) {
            $this->assertCount(3, $pool);
        }
        $planner->rebalanceStored($draft);
        $this->assertSame('ready', $draft->fresh()->status);
        foreach ($draft->fresh()->proposals as $row) {
            if ($row['meal_type'] !== 'breakfast') {
                $this->assertSame(10, MealPreparationTime::total($row['snapshot']));
            }
        }
        Http::assertSentCount(3);
    }

    public function test_zero_prep_and_zero_cook_are_valid_estimates_but_missing_estimates_are_not(): void
    {
        $this->assertSame(0, MealPreparationTime::total(['estimated_prep_minutes' => 0, 'estimated_cook_minutes' => 0]));
        $this->assertTrue(MealPreparationTime::fits(['estimated_prep_minutes' => 0, 'estimated_cook_minutes' => 0], 10));
        $this->assertFalse(MealPreparationTime::fits([], 10));
    }

    public static function preferredTimes(): array
    {
        return ['10 minutes' => [10], '15 minutes' => [15], '20 minutes' => [20], '30 minutes' => [30]];
    }

    #[DataProvider('preferredTimes')]
    public function test_all_time_preferences_allow_honest_over_time_ideas_and_weekly_previews_without_time_retries(int $minutes): void
    {
        $user = $this->member(3, 0, $minutes);
        $this->fakeMeals($user, $this->foods(), 12, 28);
        $targets = app(WeeklyMealPlanner::class)->source($user)['context']['targets'];
        $this->actingAs($user)->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $this->get(route('meal-ideas.index'))->assertOk()->assertSee('Estimated total time: 40 minutes')->assertSee('This meal may take longer than your preferred time.');
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        app(MealPortionBalancer::class)->assertFits($draft->fresh()->proposals, [], $targets);
        foreach ($draft->fresh()->proposals as $row) {
            $this->assertSame(12, $row['snapshot']['estimated_prep_minutes']);
            $this->assertSame(28, $row['snapshot']['estimated_cook_minutes']);
        }
        Http::assertSentCount(4);
        $this->assertDatabaseCount('saved_meals', 0);
        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_closest_estimated_times_are_preferred_for_ideas_and_weeks_when_none_fit(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets']);
            foreach ($recipes as $index => &$recipe) {
                $recipe['estimated_prep_minutes'] = 5;
                $recipe['estimated_cook_minutes'] = [40, 20, 15][$index];
            }
            unset($recipe);

            return Http::response($this->response($recipes));
        }]);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $this->assertSame([20, 25, 45], array_map(fn ($idea) => MealPreparationTime::total($idea), $batch['ideas']));
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        foreach ($draft->fresh()->proposals as $row) {
            if ($row['meal_type'] !== 'breakfast') {
                $this->assertSame(20, MealPreparationTime::total($row['snapshot']));
            }
        }
        Http::assertSentCount(4);
    }

    public function test_weekly_missing_timing_is_accepted_and_displayed_without_fabricating_estimates(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);

            return Http::response($this->response($this->recipes($foods, app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets'])));
        }]);
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        foreach ($draft->fresh()->proposals as $row) {
            $this->assertNull(MealPreparationTime::total($row['snapshot']));
        }
        $this->actingAs($user)->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('Time unavailable')->assertDontSee('This meal may take longer than your preferred time.');
        Http::assertSentCount(3);
    }

    public function test_time_preference_does_not_allow_excluded_ingredients_or_add_schema_retries(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $foods['Fat B']->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $this->assertNotContains($foods['Fat B']->id, array_column($input['ingredient_catalog'], 'id'));
            $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, 'lunch')['meal_targets']);
            foreach ($recipes as &$recipe) {
                $recipe['estimated_prep_minutes'] = 10;
                $recipe['estimated_cook_minutes'] = 30;
            }
            unset($recipe);

            return Http::response($this->response($recipes));
        }]);
        $this->actingAs($user)->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasErrors('generation');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public function test_swaps_prefer_closest_times_keep_unknown_options_and_exclusions_and_preserve_saved_portions(): void
    {
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        $this->fakeMeals($user, $foods);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $helper = app(PlannedMealSnapshot::class);
        $meals = [];
        foreach ([40, 20, null] as $index => $minutes) {
            $snapshot = $batch['ideas'][$index];
            $snapshot['name'] = ['Longer meal', 'Closest meal', 'Unknown time'][$index];
            unset($snapshot['estimated_prep_minutes'], $snapshot['estimated_cook_minutes']);
            if ($minutes !== null) {
                $snapshot['estimated_prep_minutes'] = 5;
                $snapshot['estimated_cook_minutes'] = $minutes - 5;
            }
            $meals[] = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => $helper->hash($snapshot), 'snapshot' => $snapshot]);
        }
        $currentSnapshot = $batch['ideas'][0];
        $currentSnapshot['instructions'] = 'Prepare the current recipe using a different method.';
        $entry = MealPlanEntry::create(['user_id' => $user->id, 'plan_date' => '2026-11-09', 'slot' => 'lunch', 'snapshot' => $currentSnapshot]);
        $forbidden = $foods['Protein']->replicate();
        $forbidden->food_code = 'Forbidden';
        $forbidden->name = 'Forbidden';
        $forbidden->save();
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $forbidden->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $bad = $batch['ideas'][0];
        $bad['name'] = 'Excluded fast meal';
        $bad['ingredients'][0]['ingredient_id'] = $forbidden->id;
        $bad['ingredients'][0]['name'] = $forbidden->name;
        $bad['ingredients'][0]['food_key'] = MealIdeaGenerator::foodKey($forbidden);
        $bad['estimated_prep_minutes'] = 1;
        $bad['estimated_cook_minutes'] = 1;
        $excluded = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => $helper->hash($bad), 'snapshot' => $bad]);
        $response = $this->actingAs($user)->get(route('meal-plan.swap', $entry))->assertOk()
            ->assertSee('This meal may take longer than your preferred time.')->assertSee('Time unavailable')->assertDontSee('Excluded fast meal');
        $this->assertSame(['Closest meal', 'Longer meal', 'Unknown time'], array_map(fn ($meal) => $meal->snapshot['name'], $response->viewData('meals')->items()));
        $token = $helper->entryToken($entry);
        $this->put(route('meal-plan.swap.update', $entry), ['saved_meal_id' => $excluded->id, 'entry_token' => $token])->assertSessionHasErrors('meal');
        $this->put(route('meal-plan.swap.update', $entry), ['saved_meal_id' => $meals[1]->id, 'entry_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame($meals[1]->snapshot, $entry->fresh()->snapshot);
        $this->assertSame(20, MealPreparationTime::total($entry->fresh()->snapshot));
        Http::assertSentCount(1);
    }

    public static function unknownTimes(): array
    {
        return ['unknown' => [null, null], 'partial' => [5, null], 'invalid estimate' => ['quick', -1]];
    }

    #[DataProvider('unknownTimes')]
    public function test_unusable_timing_metadata_does_not_reject_eligible_nutrition_or_trigger_a_retry(mixed $prep, mixed $cook): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, 'lunch')['meal_targets']);
        foreach ($recipes as &$recipe) {
            $recipe['estimated_prep_minutes'] = $prep;
            $recipe['estimated_cook_minutes'] = $cook;
        }
        unset($recipe);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->response($recipes))]);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $this->assertCount(3, $batch['ideas']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertNull(MealPreparationTime::total($idea));
        }
        Http::assertSentCount(1);
    }

    public function test_a_slower_numerically_feasible_option_remains_available_when_fast_candidates_cannot_meet_targets(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $target = app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets'];
            $recipes = $this->recipes($foods, $target);
            foreach ($recipes as $index => &$recipe) {
                $recipe['estimated_prep_minutes'] = 2;
                $recipe['estimated_cook_minutes'] = $index === 2 ? 38 : 3;
                if ($index !== 2) {
                    $recipe['ingredients'] = array_values(array_filter($recipe['ingredients'], fn ($row) => ! in_array($row['ingredient_id'], [$foods['Fat A']->id, $foods['Fat B']->id], true)));
                }
            }
            unset($recipe);

            return Http::response($this->response($recipes));
        }]);
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertContains(40, array_map(fn ($row) => MealPreparationTime::total($row['snapshot']), $draft->fresh()->proposals));
        $source = app(WeeklyMealPlanner::class)->source($user);
        app(MealPortionBalancer::class)->assertFits($draft->fresh()->proposals, [], $source['context']['targets']);
        Http::assertSentCount(3);
    }

    public function test_individual_ideas_rank_within_time_before_longer_and_unknown_estimates(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, 'lunch')['meal_targets']);
        foreach ($recipes as $index => &$recipe) {
            $recipe['estimated_prep_minutes'] = [5, 3, null][$index];
            $recipe['estimated_cook_minutes'] = [15, 5, null][$index];
        }
        unset($recipe);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->response($recipes))]);
        $batch = app(MealIdeaBatchGenerator::class)->generate($user, 'lunch');
        $this->assertSame([8, 20, null], array_map(fn ($idea) => MealPreparationTime::total($idea), $batch['ideas']));
        Http::assertSentCount(1);
    }

    public function test_breakfast_selector_uses_distinct_suitable_options_even_when_one_is_quicker(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets']);
            foreach ($recipes as $index => &$recipe) {
                $recipe['estimated_prep_minutes'] = 5;
                $recipe['estimated_cook_minutes'] = $index === 0 ? 5 : 20;
            }
            unset($recipe);

            return Http::response($this->response($recipes));
        }]);
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertCount(3, $draft->fresh()->generated['breakfast']);
        $breakfasts = array_filter($draft->fresh()->proposals, fn ($row) => $row['meal_type'] === 'breakfast');
        $this->assertCount(3, array_unique(array_map(fn ($row) => $row['snapshot']['name'], $breakfasts)));
        app(MealPortionBalancer::class)->assertFits($draft->fresh()->proposals, [], app(WeeklyMealPlanner::class)->source($user)['context']['targets']);
        Http::assertSentCount(3);
    }

    public function test_a_single_stored_breakfast_option_can_repeat_without_extra_generation(): void
    {
        $user = $this->member(3, 0, 10);
        $this->fakeMeals($user, $this->foods());
        $draft = $this->draft($user);
        $planner = app(WeeklyMealPlanner::class);
        (new GenerateWeeklyMealDraft($draft->id))->handle($planner);
        $pools = $draft->fresh()->generated;
        $pools['breakfast'] = [$pools['breakfast'][0]];
        $draft->update(['status' => 'failed', 'generated' => $pools]);
        $planner->rebalanceStored($draft);
        $this->assertSame('ready', $draft->fresh()->status);
        $breakfasts = array_filter($draft->fresh()->proposals, fn ($row) => $row['meal_type'] === 'breakfast');
        $this->assertCount(7, $breakfasts);
        $this->assertCount(1, array_unique(array_map(fn ($row) => $row['snapshot']['name'], $breakfasts)));
        Http::assertSentCount(3);
    }

    public function test_breakfast_variety_never_forces_an_option_that_cannot_meet_the_nutrition_checks(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $user = $this->member(3, 0, 10);
        $foods = $this->foods();
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $recipes = $this->recipes($foods, app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets']);
            if ($brief['meal_type'] === 'breakfast') {
                foreach ($recipes as $index => &$recipe) {
                    if ($index !== 0) {
                        $recipe['ingredients'] = array_values(array_filter($recipe['ingredients'], fn ($row) => $row['ingredient_id'] !== $foods['Protein']->id));
                    }
                }
                unset($recipe);
            }
            foreach ($recipes as &$recipe) {
                $recipe['estimated_prep_minutes'] = 5;
                $recipe['estimated_cook_minutes'] = 5;
            }
            unset($recipe);

            return Http::response($this->response($recipes));
        }]);
        $draft = $this->draft($user);
        (new GenerateWeeklyMealDraft($draft->id))->handle(app(WeeklyMealPlanner::class));
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertCount(3, $draft->fresh()->generated['breakfast']);
        $breakfasts = array_filter($draft->fresh()->proposals, fn ($row) => $row['meal_type'] === 'breakfast');
        $this->assertSame(['Option 0'], array_values(array_unique(array_map(fn ($row) => $row['snapshot']['name'], $breakfasts))));
        app(MealPortionBalancer::class)->assertFits($draft->fresh()->proposals, [], app(WeeklyMealPlanner::class)->source($user)['context']['targets']);
        Http::assertSentCount(3);
    }
}
