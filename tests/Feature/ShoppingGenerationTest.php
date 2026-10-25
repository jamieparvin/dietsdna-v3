<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\MealPlanWeekState;
use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\WeeklyMealPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShoppingGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_and_weekly_generation_only_send_active_purchase_ready_foods_and_preserve_history(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        $owner = User::factory()->create();
        $owner->nutritionProfile()->create(['date_of_birth' => '1996-01-01', 'height_cm' => 180,
            'weight_kg' => 80, 'calculation_sex' => 'male', 'activity_level' => 'moderate',
            'goal' => 'maintain_weight', 'planning_context' => 'general_adult', 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0, 'dietary_preference' => 'no_preference']);
        $foods = collect();
        foreach ([['raw', true], ['dry', true], ['ready_to_eat', true], ['raw', false], ['boiled', false], ['roasted', true]] as $i => [$preparation, $active]) {
            $foods->push(Ingredient::create(['food_code' => 'GEN-SHOP-'.$i, 'name' => 'Food '.$i,
                'category' => 'Vegetables', 'sub_category' => 'Test', 'preparation_state' => $preparation,
                'energy_kcal' => 200, 'protein_g' => 20, 'carbohydrate_g' => 20, 'sugars_g' => 1,
                'fat_g' => 5, 'saturates_g' => 1, 'fibre_g' => 2, 'salt_g' => 0, 'is_active' => $active]));
        }
        $allowed = $foods->take(3);
        $historical = MealPlanEntry::create(['user_id' => $owner->id, 'saved_meal_id' => null,
            'plan_date' => '2026-10-26', 'slot' => 'old_slot', 'snapshot' => ['name' => 'Historical cooked meal',
                'ingredients' => [['ingredient_id' => $foods[4]->id, 'name' => $foods[4]->name,
                    'preparation_state' => 'boiled', 'quantity_g' => 200]]]]);
        $before = $historical->fresh()->getRawOriginal();
        $recipes = array_map(fn ($n) => ['name' => 'Meal '.$n, 'description' => 'Test meal', 'servings' => 1,
            'instructions' => 'Weigh raw and dry ingredients before cooking. Serve ready-to-eat food as supplied.',
            'ingredients' => $allowed->map(fn ($food) => ['ingredient_id' => $food->id, 'quantity_g' => 100])->all()], [1, 2, 3]);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [[
            'type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => $recipes])]],
        ]]])]);
        $generator = app(MealIdeaGenerator::class);
        $this->assertSame($allowed->pluck('id')->all(), $generator->context($owner)['ingredients']->pluck('id')->all());
        $batch = $generator->generate($owner, 'lunch');
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame(100.0, $batch['ideas'][0]['ingredients'][1]['quantity_g']);

        // Exercise the weekly orchestration with real catalog filtering and mocked
        // transport; numeric optimization is covered by its existing suites.
        $this->mock(MealPortionBalancer::class, function ($mock) {
            $mock->shouldReceive('balance')->andReturnUsing(fn ($proposals) => $proposals);
            $mock->shouldReceive('assertFits')->andReturnNull();
        });
        $planner = app(WeeklyMealPlanner::class);
        $source = $planner->source($owner);
        $draft = WeeklyMealDraft::create(['user_id' => $owner->id, 'start_date' => '2026-10-26',
            'status' => 'generating', 'source_signature' => $source['signature'], 'meal_count' => 3,
            'slots' => $source['slots'], 'mode' => 'replace',
            'baseline_fingerprint' => app(MealPlanWeekState::class)->capture($owner->id, CarbonImmutable::parse('2026-10-26'))['fingerprint']]);
        $planner->generate($draft);
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertCount(21, $draft->fresh()->proposals);
        Http::assertSentCount(4);
        foreach (Http::recorded() as [$request, $response]) {
            $input = json_decode($request['input'], true);
            $this->assertSame($allowed->pluck('id')->all(), array_column($input['ingredient_catalog'], 'id'));
            $this->assertStringContainsString('Raw and dry ingredient entries mean quantities are weighed before cooking.', $request['instructions']);
            $this->assertStringContainsString('Ready-to-eat entries are weighed as served', $request['instructions']);

        }
        $this->assertSame($before, $historical->fresh()->getRawOriginal());
        $oldBatch = ['signature' => 'previous-catalog', 'meal_type' => 'lunch', 'created_at' => now()->timestamp,
            'portion_version' => MealIdeaPortionBalancer::VERSION, 'balancer_version' => MealPortionBalancer::VERSION,
            'ideas' => [['idea_portion_balance' => ['version' => MealIdeaPortionBalancer::VERSION,
                'balancer_version' => MealPortionBalancer::VERSION]]]];
        $key = 'meal_ideas.user_'.$owner->id;
        $this->actingAs($owner)->withSession([$key => $oldBatch])->get(route('meal-ideas.index'))
            ->assertOk()->assertSessionHas($key, $oldBatch);
        $this->assertSame($before, $historical->fresh()->getRawOriginal());
    }
}
