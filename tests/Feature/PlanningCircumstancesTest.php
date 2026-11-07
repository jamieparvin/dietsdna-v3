<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanningCircumstancesTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $changes): User
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create(array_replace([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'specialist_support', 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ], $changes));
        foreach (['Rice', 'Carrot', 'Chicken'] as $name) {
            Ingredient::create(['food_code' => $name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                'energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10, 'sugars_g' => 0, 'fat_g' => 2, 'saturates_g' => 0, 'fibre_g' => 2, 'salt_g' => 0]);
        }

        return $user;
    }

    public static function remainingRestrictions(): array
    {
        return ['allergy' => [['allergies' => 'Recorded allergy'], 'recorded allergy or food restriction'],
            'recorded food restriction' => [['foods_to_avoid' => 'Recorded restriction'], 'recorded allergy or food restriction'],
            'measurement range' => [['weight_kg' => 100], 'Your measurements fall outside the range supported by automatic planning']];
    }

    #[DataProvider('remainingRestrictions')]
    public function test_other_generation_blocks_are_explained_before_the_weekly_link_without_redirect_loops(array $changes, string $reason): void
    {
        Http::preventStrayRequests();
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $user = $this->member($changes);
        $this->actingAs($user)->get(route('setup'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee($reason)->assertDontSee('Continue to weekly preview')->assertSee('View nutrition profile');
        $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Important: food allergies')->assertDontSee('Do you need specialist nutrition support?');
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee($reason)->assertDontSee('id="weekly-start-form"', false);
        $this->postJson(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertUnprocessable();
        $this->postJson(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertUnprocessable();
        $this->assertDatabaseCount('weekly_meal_drafts', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_ingredient_shortage_is_explained_on_setup_before_sending_the_user_to_generate_a_week(): void
    {
        Http::preventStrayRequests();
        $user = $this->member([]);
        Ingredient::query()->update(['is_active' => false]);
        $this->actingAs($user)->get(route('setup'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('not enough eligible ingredients')->assertDontSee('Continue to weekly preview');
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        Http::assertNothingSent();
    }

    public function test_unanswered_retired_question_does_not_bypass_missing_measurements_or_unsaved_preferences(): void
    {
        $user = $this->member(['planning_context' => null, 'height_cm' => null]);
        $this->actingAs($user)->get(route('setup'))->assertRedirect(route('nutrition-profile.edit'));
        $this->post(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertRedirect(route('nutrition-profile.edit'));
        $this->put(route('nutrition-profile.update'), ['height_cm' => 180])->assertSessionHasNoErrors();
        $user->forceFill(['food_preferences_reviewed_at' => null])->save();
        $this->get(route('setup'))->assertRedirect(route('food-preferences.edit'));
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertRedirect(route('food-preferences.edit'));
    }
}
