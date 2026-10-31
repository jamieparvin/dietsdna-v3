<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\ProteinVariety;
use App\Services\Meals\WeeklyMealSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProteinVarietyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private $foods;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.openai.key' => 'fake', 'services.openai.recipe_model' => 'fake']);
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create(['date_of_birth' => '1996-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false, 'meals_per_day' => 3, 'snacks_per_day' => 1]);
        $this->foods = collect();
        foreach ([['SALMON-RAW-001', 'raw'], ['SALMON-SMOKED-001', 'ready_to_eat'], ['COD-RAW-001', 'raw'],
            ['TUNA-TINNED-IN-WATER-DRAINED-001', 'ready_to_eat'], ['RICE', 'dry'], ['OIL', 'ready_to_eat']] as $i => [$code, $form]) {
            $this->foods->push(Ingredient::create(['food_code' => $code, 'name' => 'Food '.$i,
                'category' => $i < 4 ? 'Fish & seafood' : 'Carbohydrates', 'sub_category' => 'Test',
                'preparation_state' => $form, 'energy_kcal' => $i < 4 ? 290 : ($i === 4 ? 400 : 900),
                'protein_g' => $i < 4 ? 50 : 0, 'carbohydrate_g' => $i === 4 ? 100 : 0, 'fat_g' => $i < 4 ? 10 : ($i === 5 ? 100 : 0),
                'sugars_g' => 0, 'saturates_g' => 0, 'fibre_g' => 0, 'salt_g' => 0, 'is_active' => true]));
        }
        $this->actingAs($this->owner)->put(route('food-preferences.update'), ['preferences' => $this->foods->take(4)
            ->mapWithKeys(fn ($f) => [$f->preferenceFoodKey() => 'preferred'])->all()])->assertSessionHasNoErrors();
    }

    private function recipes(array $indices, string $prefix = 'Option', bool $badCod = false): array
    {
        $targets = app(MealIdeaGenerator::class)->context($this->owner, 'lunch')['meal_targets'];

        return array_map(function ($index, $n) use ($targets, $prefix, $badCod) {
            $rows = [['ingredient_id' => $this->foods[$index]->id, 'quantity_g' => round($targets['protein_g'] * 2, 2)],
                ['ingredient_id' => $this->foods[4]->id, 'quantity_g' => round($targets['carbohydrate_g'], 2)],
                ['ingredient_id' => $this->foods[5]->id, 'quantity_g' => round($targets['fat_g'] - $targets['protein_g'] * 0.2, 2)]];
            if ($badCod && $index === 2) {
                array_pop($rows);
            }

            return ['name' => $prefix.' '.$n, 'description' => 'Test meal', 'servings' => 1,
                'instructions' => ['Combine the listed ingredients.', 'Layer the listed ingredients.', 'Arrange the listed ingredients in a bowl.'][$n],
                'ingredients' => $rows];
        }, $indices, array_keys($indices));
    }

    private function response(array $recipes): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [
            ['type' => 'output_text', 'text' => json_encode(['recipes' => $recipes])]]]]];
    }

    public function test_three_liked_proteins_are_guided_and_accepted_without_treating_salmon_preparations_as_different_sources(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->response($this->recipes([0, 2, 3])))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $batch = session('meal_ideas.user_'.$this->owner->id);
        $this->assertCount(3, $batch['ideas']);
        $catalog = $this->foods->keyBy('id')->map->getAttributes()->all();
        $sources = array_map(fn ($idea) => app(ProteinVariety::class)->main($idea, $catalog), $batch['ideas']);
        $this->assertCount(3, array_unique($sources));
        foreach ($batch['ideas'] as $idea) {
            app(MealIdeaPortionBalancer::class)->assertSavable($idea, app(MealIdeaGenerator::class)->context($this->owner));
        }
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $groups = $brief['protein_variety_groups'];
            $this->assertCount(3, $groups);
            $this->assertCount(3, array_unique($brief['preferred_protein_sources']));
            $salmon = collect($groups)->firstWhere('source', 'reviewed:salmon');
            $this->assertSame([$this->foods[0]->id, $this->foods[1]->id], $salmon['ingredient_ids']);
            $this->assertTrue($salmon['liked']);

            return true;
        });
        $this->assertNotSame($this->foods[0]->fresh()->preferenceFoodKey(), $this->foods[1]->fresh()->preferenceFoodKey());
        $context = app(MealIdeaGenerator::class)->context($this->owner);
        $groups = app(ProteinVariety::class)->guidance($context['variety_catalog'], 'lunch', ['reviewed:salmon' => 10]);
        $this->assertSame('reviewed:salmon', $groups[array_key_last($groups)]['source']);

        Http::assertSentCount(1);
    }

    public function test_single_suitable_source_can_repeat_without_spending_an_extra_request_for_variety(): void
    {
        $this->foods[2]->update(['is_active' => false]);
        $this->foods[3]->update(['is_active' => false]);
        Http::fake(['api.openai.com/*' => Http::response($this->response($this->recipes([0, 1, 0])))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $this->assertCount(3, session('meal_ideas.user_'.$this->owner->id)['ideas']);
        Http::assertSentCount(1);
    }

    public function test_excluded_food_is_not_offered_and_unreviewed_group_is_flagged_without_merging_choices(): void
    {
        $this->put(route('food-preferences.update'), ['preferences' => [$this->foods[2]->preferenceFoodKey() => 'excluded']])->assertSessionHasNoErrors();
        $this->foods[3]->update(['food_code' => 'NEW-UNREVIEWED-PREPARATION']);
        $context = app(MealIdeaGenerator::class)->context($this->owner);
        $this->assertContains($this->foods[3]->name, $context['variety_warnings']);
        $groups = app(ProteinVariety::class)->guidance($context['variety_catalog'], 'lunch', []);
        $this->assertNotContains($this->foods[2]->id, collect($groups)->pluck('ingredient_ids')->flatten()->all());
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee('Protein variety grouping needs review for:');
        $this->get(route('meal-ideas.index'))->assertOk()->assertSee('Protein variety grouping needs review for:');
        $this->assertSame('excluded', DB::table('user_food_preferences')->where('user_id', $this->owner->id)
            ->where('food_key', $this->foods[2]->preferenceFoodKey())->value('preference'));
    }

    public function test_variety_never_rescues_a_macro_failure_and_replacements_can_repeat_valid_sources(): void
    {
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->response($this->recipes([0, 2, 3], 'First', true)))
            ->push($this->response($this->recipes([0, 0, 0], 'Replacement')))]);
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasNoErrors();
        $batch = session('meal_ideas.user_'.$this->owner->id);
        $this->assertCount(3, $batch['ideas']);
        $this->assertSame(1, $batch['rejected_count']);
        foreach ($batch['ideas'] as $idea) {
            $this->assertNotContains($this->foods[2]->id, array_column($idea['ingredients'], 'ingredient_id'));
            app(MealIdeaPortionBalancer::class)->assertSavable($idea, app(MealIdeaGenerator::class)->context($this->owner));
        }
        Http::assertSentCount(2);
    }

    public function test_fish_is_not_a_suggested_protein_assignment_for_breakfast_or_snacks(): void
    {
        $context = app(MealIdeaGenerator::class)->context($this->owner);
        $this->assertSame([], app(ProteinVariety::class)->guidance($context['variety_catalog'], 'breakfast', []));
        $this->assertSame([], app(ProteinVariety::class)->guidance($context['variety_catalog'], 'snack', []));
    }

    public function test_weekly_selection_counts_existing_salmon_and_both_preparations_as_one_source(): void
    {
        $catalog = $this->foods->keyBy('id')->map->getAttributes()->all();
        $pool = [];
        foreach ([0, 1, 3] as $index) {
            $idea = ['name' => 'Same arbitrary title', 'description' => 'Test meal', 'instructions' => 'Combine the listed ingredients.',
                'ingredients' => array_map(fn ($i, $g) => ['name' => $this->foods[$i]->name, 'preparation_state' => $this->foods[$i]->preparation_state,
                    'quantity_g' => $g], [$index, 4, 5], [60, 50, 14]),
                'totals' => ['energy_kcal' => 500, 'protein_g' => 30, 'carbohydrate_g' => 50, 'fat_g' => 20,
                    'sugars_g' => 0, 'saturates_g' => 0, 'fibre_g' => 0, 'salt_g' => 0]];
            $pool[] = app(PlannedMealSnapshot::class)->fromIdea($idea, $this->foods);
        }
        $fixed = [['date' => '2026-11-02', 'slot' => 'lunch', 'snapshot' => $pool[0]],
            ['date' => '2026-11-03', 'slot' => 'lunch', 'snapshot' => $pool[1]]];
        $before = $fixed;
        $targets = ['calories_kcal' => 500, 'protein_g' => 30, 'carbohydrate_g' => 50, 'fat_g' => 20];
        $result = app(WeeklyMealSchedule::class)->buildBalanced('2026-11-02', ['lunch' => 'Lunch'], ['lunch' => $pool],
            $fixed, $catalog, $targets, app(MealPortionBalancer::class));
        $variety = app(ProteinVariety::class);
        $this->assertSame('reviewed:tuna', $variety->main($result[0]['snapshot'], $catalog));
        $uses = $variety->usage(array_merge($fixed, $result), $catalog);
        $this->assertLessThanOrEqual(1, abs($uses['reviewed:salmon'] - $uses['reviewed:tuna']));
        $this->assertSame($before, $fixed);
        app(MealPortionBalancer::class)->assertFits($result, $fixed, $targets);
        // Less-used does not mean feasible: cod without any added fat cannot
        // meet the unchanged macro guide. Salmon must remain a valid fallback.
        $invalid = $pool[0];
        $invalid['ingredients'][0]['ingredient_id'] = $this->foods[2]->id;
        $invalid['ingredients'][0]['food_key'] = $this->foods[2]->snapshotFoodKey();
        array_pop($invalid['ingredients']);
        $onlyValid = app(WeeklyMealSchedule::class)->buildBalanced('2026-11-02', ['lunch' => 'Lunch'],
            ['lunch' => [$pool[0], $invalid]], $fixed, $catalog, $targets, app(MealPortionBalancer::class));
        foreach ($onlyValid as $row) {
            $this->assertSame('reviewed:salmon', $variety->main($row['snapshot'], $catalog));
        }
        app(MealPortionBalancer::class)->assertFits($onlyValid, $fixed, $targets);
        $repeated = app(WeeklyMealSchedule::class)->buildBalanced('2026-11-02', ['lunch' => 'Lunch', 'meal_2' => 'Lunch'],
            ['lunch' => [$pool[0]]], [], $catalog,
            ['calories_kcal' => 1000, 'protein_g' => 60, 'carbohydrate_g' => 100, 'fat_g' => 40], app(MealPortionBalancer::class));
        $this->assertCount(14, $repeated);

        // A single option can fill multiple slots when variety is impossible.
        $rows = app(WeeklyMealSchedule::class)->build('2026-11-02', ['lunch' => 'Lunch', 'meal_2' => 'Lunch'], ['lunch' => [$pool[0]]], []);
        $this->assertCount(14, $rows);
    }
}
