<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealIdeaPortionBalancer;
use App\Services\Meals\MealPortionBalancer;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Meals\WeeklyMealPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoodPreferencePlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->owner->nutritionProfile()->create([
            'date_of_birth' => '1996-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false, 'meals_per_day' => 3, 'snacks_per_day' => 0,
            'dietary_preference' => 'vegan',
        ]);
        foreach (['Beef', 'Milk', 'Rice', 'Carrot'] as $name) {
            Ingredient::create(['food_code' => $name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test',
                'preparation_state' => 'raw', 'energy_kcal' => 100, 'protein_g' => 10,
                'carbohydrate_g' => 10, 'sugars_g' => 0, 'fat_g' => 2, 'saturates_g' => 0,
                'fibre_g' => 2, 'salt_g' => 0, 'is_active' => true]);
        }
    }

    public function test_retired_diet_does_not_filter_catalog_or_change_individual_or_weekly_signatures(): void
    {
        $generator = app(MealIdeaGenerator::class);
        $planner = app(WeeklyMealPlanner::class);
        $context = $generator->context($this->owner);
        $source = $planner->source($this->owner);
        $this->assertSame(['Beef', 'Milk', 'Rice', 'Carrot'], $context['ingredients']->pluck('name')->all());
        $this->assertArrayNotHasKey('diet', $context);
        $this->travel(1)->minutes();
        $this->owner->nutritionProfile()->update(['dietary_preference' => 'historical-unrecognised-value']);
        $this->assertSame($context['signature'], $generator->context($this->owner)['signature']);
        $this->assertSame($source['signature'], $planner->source($this->owner)['signature']);

        $food = Ingredient::where('name', 'Beef')->firstOrFail();
        $this->actingAs($this->owner)->put(route('food-preferences.update'), [
            'preferences' => [$food->preferenceFoodKey() => 'excluded'],
        ])->assertSessionHasNoErrors();
        $this->assertNotContains($food->id, $generator->context($this->owner)['ingredients']->pluck('id')->all());
        $this->assertNotSame($source['signature'], $planner->source($this->owner)['signature']);
        Http::assertNothingSent();
    }

    public function test_profile_ignores_retired_fields_and_preserves_recorded_restrictions(): void
    {
        $profile = $this->owner->nutritionProfile;
        $profile->update(['allergies' => 'Recorded peanut allergy', 'foods_to_avoid' => 'Recorded restriction']);
        $this->actingAs($this->owner)->put(route('nutrition-profile.update'), [
            'weight_kg' => 82, 'dietary_preference' => ['invalid'], 'allergies' => '', 'foods_to_avoid' => null,
        ])->assertSessionHasNoErrors()->assertRedirect(route('nutrition-profile.edit'));
        $this->assertSame('vegan', $profile->fresh()->dietary_preference);
        $this->assertSame('Recorded peanut allergy', $profile->fresh()->allergies);
        $this->assertSame('Recorded restriction', $profile->fresh()->foods_to_avoid);
        $this->assertEquals(82, $profile->fresh()->weight_kg);
        $this->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Important: food allergies')->assertSee('DietsDNA does not currently support meal planning for food allergies.')
            ->assertSee('account has a recorded allergy or food restriction')
            ->assertDontSee('name="dietary_preference"', false)->assertDontSee('name="allergies"', false)
            ->assertDontSee('name="foods_to_avoid"', false);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.users.show', $this->owner))->assertOk()
            ->assertSee('Recorded peanut allergy')->assertSee('Recorded restriction');
    }

    public function test_new_profile_cannot_acquire_retired_fields_from_submissions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('nutrition-profile.update'), [
            'weight_kg' => 80, 'dietary_preference' => 'vegan', 'allergies' => 'Injected', 'foods_to_avoid' => 'Injected',
        ])->assertSessionHasNoErrors();
        $profile = $user->nutritionProfile()->firstOrFail();
        $this->assertNull($profile->dietary_preference);
        $this->assertNull($profile->allergies);
        $this->assertNull($profile->foods_to_avoid);
    }

    public function test_recorded_restriction_is_explained_even_when_calculation_profile_is_incomplete(): void
    {
        $this->owner->nutritionProfile()->update(['height_cm' => null, 'allergies' => 'Recorded allergy']);
        $this->actingAs($this->owner)->get(route('meal-ideas.index'))->assertRedirect(route('nutrition-profile.edit'));
        $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('account has a recorded allergy or food restriction');
        Http::assertNothingSent();
    }

    public static function restrictions(): array
    {
        return [['allergies'], ['foods_to_avoid']];
    }

    #[DataProvider('restrictions')]
    public function test_recorded_restrictions_block_generation_saving_adding_swapping_and_confirmation(string $field): void
    {
        $source = app(WeeklyMealPlanner::class)->source($this->owner);
        $draft = WeeklyMealDraft::create(['user_id' => $this->owner->id, 'start_date' => now()->startOfWeek()->toDateString(),
            'status' => 'ready', 'source_signature' => $source['signature'], 'meal_count' => 3,
            'slots' => $source['slots'], 'mode' => 'fill', 'proposals' => []]);
        $snapshot = ['name' => 'Historical meal', 'description' => 'Preserved snapshot', 'instructions' => 'Serve.', 'ingredients' => [], 'totals' => []];
        $meal = SavedMeal::create(['user_id' => $this->owner->id, 'meal_type' => 'lunch',
            'content_hash' => hash('sha256', 'historical'), 'snapshot' => $snapshot]);
        $entry = MealPlanEntry::create(['user_id' => $this->owner->id, 'saved_meal_id' => $meal->id,
            'plan_date' => now()->toDateString(), 'slot' => 'lunch', 'snapshot' => $snapshot]);
        $before = $entry->fresh()->getRawOriginal();
        $batch = ['created_at' => now()->timestamp, 'signature' => $source['context']['signature'], 'meal_type' => 'lunch',
            'portion_version' => MealIdeaPortionBalancer::VERSION, 'balancer_version' => MealPortionBalancer::VERSION,
            'ideas' => [['idea_portion_balance' => ['version' => MealIdeaPortionBalancer::VERSION,
                'balancer_version' => MealPortionBalancer::VERSION]]]];
        $this->owner->nutritionProfile()->update([$field => 'Recorded restriction']);
        $this->actingAs($this->owner)->get(route('meal-ideas.index'))->assertOk()->assertSee('account has a recorded allergy or food restriction');
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee('account has a recorded allergy or food restriction');
        $this->get(route('meal-plan.swap', $entry))->assertOk()->assertSee('account has a recorded allergy or food restriction');
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertSessionHasErrors('generation');
        $this->post(route('weekly-meals.store'), ['start_date' => now()->startOfWeek()->toDateString(), 'mode' => 'replace'])->assertSessionHasErrors('generation');
        $this->withSession(['meal_ideas.user_'.$this->owner->id => $batch])->post(route('saved-meals.store'), [
            'idea_index' => 0, 'batch_token' => hash('sha256', json_encode($batch, JSON_THROW_ON_ERROR)),
        ])->assertSessionHasErrors('generation');
        $this->post(route('meal-plan.store'), ['saved_meal_id' => $meal->id, 'plan_date' => now()->toDateString(), 'slot' => 'lunch'])->assertSessionHasErrors('generation');
        $this->put(route('meal-plan.swap.update', $entry), ['saved_meal_id' => $meal->id,
            'entry_token' => app(PlannedMealSnapshot::class)->entryToken($entry)])->assertSessionHasErrors('generation');
        $this->post(route('weekly-meals.confirm', $draft))->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('saved_meals', 1);
        $this->assertDatabaseCount('meal_plan_entries', 1);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertSame('Recorded restriction', $this->owner->nutritionProfile()->first()->getAttribute($field));
        Http::assertNothingSent();
    }
}
