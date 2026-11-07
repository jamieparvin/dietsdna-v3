<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\WeeklyMealPlanner;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RetiredPlanningSuitabilityGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function profileData(): array
    {
        return ['date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'resistance_training' => false, 'meals_per_day' => 3, 'snacks_per_day' => 0];
    }

    private function foods(): array
    {
        $foods = [];
        foreach (['Protein' => [200, 50, 0, 0], 'Carb A' => [400, 0, 100, 0], 'Carb B' => [400, 0, 100, 0], 'Fat A' => [900, 0, 0, 100], 'Fat B' => [900, 0, 0, 100], 'Excluded food' => [100, 10, 10, 2]] as $name => [$kcal, $protein, $carbs, $fat]) {
            $foods[$name] = Ingredient::create(['food_code' => $name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                'energy_kcal' => $kcal, 'protein_g' => $protein, 'carbohydrate_g' => $carbs, 'fat_g' => $fat, 'fibre_g' => 0, 'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0]);
        }

        return $foods;
    }

    private function fakeMeals(User $user, array $foods): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods) {
            $input = json_decode($request['input'], true);
            $this->assertNotContains($foods['Excluded food']->id, array_column($input['ingredient_catalog'], 'id'));
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $target = app(MealIdeaGenerator::class)->context($user, $brief['meal_type'])['meal_targets'];
            $rows = [
                ['ingredient_id' => $foods['Protein']->id, 'quantity_g' => round($target['protein_g'] * 2, 2)],
                ['ingredient_id' => $foods['Carb A']->id, 'quantity_g' => round($target['carbohydrate_g'] / 2, 2)],
                ['ingredient_id' => $foods['Carb B']->id, 'quantity_g' => round($target['carbohydrate_g'] / 2, 2)],
                ['ingredient_id' => $foods['Fat A']->id, 'quantity_g' => round($target['fat_g'] / 2, 2)],
                ['ingredient_id' => $foods['Fat B']->id, 'quantity_g' => round($target['fat_g'] / 2, 2)],
            ];
            $recipes = array_map(fn ($index) => ['name' => 'Meal '.$index, 'description' => 'Test meal', 'servings' => 1,
                'instructions' => ['Combine the listed foods.', 'Layer the listed foods.', 'Arrange the listed foods.'][$index], 'ingredients' => $rows], [0, 1, 2]);

            return Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => $recipes])]]]]]);
        }]);
    }

    public static function accountTypes(): array
    {
        return ['new account never asked' => [null, null, true], 'previous Yes' => ['specialist_support', null, false],
            'previous Unsure' => ['unsure', null, false], 'legacy review' => ['needs_review', null, false],
            'previous detailed Yes' => ['specialist_support', ['pregnant', 'medical_dietary_needs'], false]];
    }

    #[DataProvider('accountTypes')]
    public function test_new_and_historical_accounts_generate_ideas_and_a_database_queued_week_without_answering_the_retired_question(?string $answer, ?array $circumstances, bool $new): void
    {
        $user = User::factory()->create();
        $foods = $this->foods();
        if (! $new) {
            $user->nutritionProfile()->create(array_merge($this->profileData(), ['planning_context' => $answer, 'planning_circumstances' => $circumstances]));
        }
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertDontSee('Do you need specialist nutrition support?');
        $this->put(route('nutrition-profile.update'), $this->profileData())->assertSessionHasNoErrors();
        $this->get(route('setup'))->assertRedirect(route('food-preferences.edit'));
        $this->put(route('food-preferences.update'), [])->assertSessionHasNoErrors();
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $foods['Excluded food']->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $preferences = DB::table('user_food_preferences')->where('user_id', $user->id)->get()->toJson();
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        $this->get(route('setup'))->assertRedirect(route('weekly-meals.create'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Continue to weekly preview');
        $profileBefore = $user->nutritionProfile()->first()->getAttributes();
        $snapshot = ['name' => 'Historical lunch', 'description' => 'Saved', 'instructions' => 'Prepare.', 'ingredients' => [], 'totals' => ['energy_kcal' => 500]];
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => hash('sha256', 'retired-question'), 'snapshot' => $snapshot]);
        $entry = MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id, 'plan_date' => '2026-10-01', 'slot' => 'lunch', 'snapshot' => $snapshot]);
        $savedBefore = $saved->fresh()->getAttributes();
        $entryBefore = $entry->fresh()->getAttributes();
        $this->fakeMeals($user, $foods);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('meal-ideas.index'))->assertOk()->assertSee('How portions were checked')->assertSee('Meal 0');
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertSessionHasNoErrors()->assertRedirect();
        $draft = WeeklyMealDraft::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('queued', $draft->status);
        $this->assertDatabaseHas('jobs', ['queue' => 'meal-plans', 'attempts' => 0]);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'meal-plans', '--once' => true, '--tries' => 1, '--sleep' => 0])->assertExitCode(0);
        $draft->refresh();
        $this->assertSame('ready', $draft->status);
        $this->assertCount(21, $draft->proposals);
        $source = app(WeeklyMealPlanner::class)->source($user);
        app(MealPortionBalancer::class)->assertFits($draft->proposals, [], $source['context']['targets']);
        $this->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('standard meal plans for general adult meal planning')->assertSee('Important: food allergies');
        $this->assertSame($profileBefore, $user->nutritionProfile()->first()->getAttributes());
        $this->assertSame($savedBefore, $saved->fresh()->getAttributes());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertSame($preferences, DB::table('user_food_preferences')->where('user_id', $user->id)->get()->toJson());
        $this->assertSame($answer, $user->nutritionProfile()->first()->planning_context);
        $this->assertSame($circumstances, $user->nutritionProfile()->first()->planning_circumstances);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        Http::assertSentCount(4);
    }

    public function test_retired_answers_are_not_new_signature_inputs_and_previous_queued_signatures_remain_compatible(): void
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create(array_merge($this->profileData(), ['planning_context' => 'general_adult']));
        $foods = $this->foods();
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $foods['Excluded food']->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $planner = app(WeeklyMealPlanner::class);
        $before = $planner->source($user);
        $draft = WeeklyMealDraft::create(['user_id' => $user->id, 'start_date' => '2026-11-09', 'status' => 'queued', 'source_signature' => $before['previousSignature'], 'meal_count' => 3, 'slots' => $before['slots']]);
        $this->assertSame($before['signature'], $planner->assertCurrent($draft, $user)['signature']);
        $this->fakeMeals($user, $foods);
        GenerateWeeklyMealDraft::dispatch($draft->id);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'meal-plans', '--once' => true, '--tries' => 1, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('ready', $draft->fresh()->status);
        $user->nutritionProfile()->first()->update(['planning_context' => 'specialist_support', 'planning_circumstances' => ['pregnant']]);
        $this->assertSame($before['signature'], $planner->source($user)['signature']);
        $this->assertSame($before['context']['signature'], $planner->source($user)['context']['signature']);
        $this->assertSame($before['context']['targets'], $planner->source($user)['context']['targets']);
    }
}
