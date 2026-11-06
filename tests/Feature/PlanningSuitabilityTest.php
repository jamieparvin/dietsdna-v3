<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Onboarding;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanningSuitabilityTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Automatic calorie targets and meal generation aren’t available for your circumstances at present. A registered dietitian or your healthcare professional can help you establish suitable nutrition targets.';

    private function member(?string $answer = null, array $changes = []): User
    {
        $user = User::factory()->create();
        $user->nutritionProfile()->create(array_replace([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => $answer, 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ], $changes));

        return $user;
    }

    private function selectedAnswers(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $choices = (new DOMXPath($document))->query('//*[@id="planning_context"]//input[@type="radio" and @checked]');

        return array_map(fn ($choice) => $choice->getAttribute('value'), iterator_to_array($choices));
    }

    private function expectedSelections(string $answer): array
    {
        return match ($answer) {
            'general_adult' => ['general_adult'],
            'unsure' => ['unsure'],
            'needs_review', 'specialist_support' => ['specialist_support'],
            default => [],
        };
    }

    public static function answers(): array
    {
        return [
            'No' => ['general_adult', 'ready', 'No — none of these apply to me'],
            'Yes' => ['specialist_support', 'review', 'Yes — one or more apply to me'],
            'Unsure' => ['unsure', 'review', 'I’m not sure'],
            'legacy Yes' => ['needs_review', 'review', 'Yes — one or more apply to me'],
        ];
    }

    public function test_new_users_have_no_preselected_answer_and_partial_saves_do_not_complete_setup(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Do you need specialist nutrition support?')
            ->assertSee('DietsDNA provides standard meal planning for adults. Pregnancy, breastfeeding and medical dietary requirements are not currently supported.');
        $this->assertSame([], $this->selectedAnswers($response->getContent()));

        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_context' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->nutritionProfile()->first()->planning_context);
        $state = app(Onboarding::class)->state($user);
        $this->assertFalse($state['profileComplete']);
        $this->assertSame('incomplete', $state['targets']['status']);
        $this->get(route('setup'))->assertRedirect(route('nutrition-profile.edit'));
        $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Complete: planning suitability answer.');
    }

    #[DataProvider('answers')]
    public function test_answers_are_saved_distinctly_and_allow_setup_completion_without_overriding_restrictions(string $answer, string $status, string $label): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_context' => $answer])->assertSessionHasNoErrors()->assertRedirect(route('setup'));
        $this->assertDatabaseHas('nutrition_profiles', ['user_id' => $user->id, 'planning_context' => $answer]);
        $targets = app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first());
        $this->assertSame($status, $targets['status']);
        if ($status === 'review') {
            $this->assertSame(self::MESSAGE, $targets['message']);
            $this->assertArrayNotHasKey('calories_kcal', $targets);
        }
        $this->get(route('setup'))->assertRedirect(route('food-preferences.edit'));
        $this->put(route('food-preferences.update'), [])->assertSessionHasNoErrors();
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        $response = $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee($label);
        $this->assertSame($this->expectedSelections($answer), $this->selectedAnswers($response->getContent()));
    }

    #[DataProvider('answers')]
    public function test_existing_answers_survive_reads_unrelated_saves_and_validation_errors(string $answer, string $status, string $label): void
    {
        $user = $this->member($answer);
        $before = $user->nutritionProfile()->first()->getAttributes();
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertSee($label);
        $this->assertSame($this->expectedSelections($answer), $this->selectedAnswers($response->getContent()));
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
        $this->put(route('nutrition-profile.update'), ['height_cm' => 180])->assertSessionHasNoErrors();
        $this->assertSame($answer, $user->nutritionProfile()->first()->planning_context);
        $this->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), ['height_cm' => 1, 'planning_context' => $answer])
            ->assertSessionHasErrors('height_cm')->assertSessionHasInput('planning_context', $answer);
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame($this->expectedSelections($answer), $this->selectedAnswers($response->getContent()));
        $this->assertSame($answer, $user->nutritionProfile()->first()->planning_context);
    }

    public function test_unknown_answers_are_rejected_without_altering_saved_data(): void
    {
        $user = $this->member('unsure');
        $before = $user->nutritionProfile()->first()->getAttributes();

        $this->actingAs($user)->putJson(route('nutrition-profile.update'), ['planning_context' => 'approved'])
            ->assertUnprocessable()->assertJsonValidationErrors('planning_context');

        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
    }

    public static function restrictedAnswers(): array
    {
        return ['Yes' => ['specialist_support'], 'Unsure' => ['unsure'], 'legacy restricted' => ['needs_review']];
    }

    #[DataProvider('restrictedAnswers')]
    public function test_restricted_answers_block_direct_generation_and_confirmation_while_preserving_existing_content(string $answer): void
    {
        Http::preventStrayRequests();
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $user = $this->member($answer);
        $user->forceFill(['food_preferences_reviewed_at' => now()])->save();
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => hash('sha256', 'suitability'), 'snapshot' => ['name' => 'Existing lunch', 'description' => 'Saved food', 'instructions' => 'Prepare the food.', 'ingredients' => [], 'totals' => []]]);
        $entry = MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id, 'plan_date' => '2026-11-09', 'slot' => 'lunch', 'snapshot' => $saved->snapshot]);
        $draft = WeeklyMealDraft::create(['user_id' => $user->id, 'start_date' => '2026-11-09', 'status' => 'ready', 'source_signature' => 'old-signature', 'meal_count' => 3, 'slots' => [], 'generated' => [], 'proposals' => []]);
        $savedBefore = $saved->fresh()->getAttributes();
        $entryBefore = $entry->fresh()->getAttributes();
        $profileBefore = $user->nutritionProfile()->first()->getAttributes();

        $this->actingAs($user)->get(route('meal-ideas.index'))->assertOk()->assertSee(self::MESSAGE);
        $this->postJson(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertUnprocessable()->assertJsonValidationErrors('generation');
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee(self::MESSAGE)->assertDontSee('id="weekly-start-form"', false);
        $this->postJson(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertUnprocessable()->assertJsonValidationErrors('generation');
        $this->postJson(route('weekly-meals.confirm', $draft), [])->assertUnprocessable();
        foreach (['nutrition-profile.edit', 'food-preferences.edit', 'saved-meals.index', 'meal-plan.index', 'progress.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('dashboard'))->assertOk()->assertSee('Automatic planning unavailable')->assertSee(self::MESSAGE)->assertDontSee('Your nutrition guide needs a review');
        $this->assertSame($savedBefore, $saved->fresh()->getAttributes());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertSame($profileBefore, $user->nutritionProfile()->first()->getAttributes());
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertDatabaseCount('weekly_meal_drafts', 1);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_unsure_with_missing_details_shows_both_incomplete_guidance_and_the_restriction_without_completing_setup(): void
    {
        $user = $this->member('unsure', ['height_cm' => null]);
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Complete: height.')->assertSee(self::MESSAGE);
        $this->assertSame(['unsure'], $this->selectedAnswers($response->getContent()));
        $this->assertFalse(app(Onboarding::class)->state($user)['profileComplete']);
        $this->get(route('weekly-meals.create'))->assertRedirect(route('nutrition-profile.edit'));
    }

    public function test_completed_restricted_setup_has_no_dashboard_prompt_to_start_generation(): void
    {
        $user = $this->member('unsure');
        $user->forceFill(['food_preferences_reviewed_at' => now()])->save();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Your setup is complete')
            ->assertSee('Automatic planning unavailable')->assertSee(self::MESSAGE)
            ->assertSee('View nutrition profile')->assertDontSee('Continue to weekly preview');
        $this->get(route('setup'))->assertRedirect(route('weekly-meals.create'));
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee(self::MESSAGE);
    }

    public function test_general_adult_answer_retains_the_existing_calculation_and_generation_eligibility(): void
    {
        Http::preventStrayRequests();
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $user = $this->member('general_adult');
        $user->forceFill(['food_preferences_reviewed_at' => now()])->save();
        $before = app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first());
        foreach (['Rice', 'Carrot', 'Chicken'] as $name) {
            Ingredient::create(['food_code' => 'SUPPORT-'.$name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                'energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10, 'sugars_g' => 0, 'fat_g' => 2, 'saturates_g' => 0, 'fibre_g' => 2, 'salt_g' => 0]);
        }

        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_context' => 'general_adult'])->assertSessionHasNoErrors();
        $this->assertSame($before, app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first()));
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee('id="weekly-start-form"', false);
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertRedirect();
        Queue::assertPushed(GenerateWeeklyMealDraft::class, 1);
        Http::assertNothingSent();
    }

    public function test_radio_answers_do_not_collect_specific_circumstances(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_context' => 'specialist_support'])->assertSessionHasNoErrors();
        $this->assertNull($user->nutritionProfile()->first()->planning_circumstances);
        $response = $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Yes — one or more apply to me');
        $this->assertSame(['specialist_support'], $this->selectedAnswers($response->getContent()));
        $this->assertStringNotContainsString('name="planning_circumstances[]"', $response->getContent());
        $this->assertStringNotContainsString('planning_circumstances_present', $response->getContent());
    }

    public function test_unchanged_radio_answer_keeps_existing_detailed_answers_and_targets(): void
    {
        $user = $this->member('specialist_support', ['planning_circumstances' => ['pregnant', 'medical_dietary_needs']]);
        $before = $user->nutritionProfile()->first()->getAttributes();
        $targets = app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first());

        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame(['specialist_support'], $this->selectedAnswers($response->getContent()));
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
        $this->put(route('nutrition-profile.update'), ['planning_context' => 'specialist_support', 'height_cm' => 180])->assertSessionHasNoErrors();
        $this->assertSame(['pregnant', 'medical_dietary_needs'], $user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame($targets, app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first()));
    }

    public function test_explicit_radio_change_overrides_old_checkbox_answers_without_stale_eligibility(): void
    {
        $user = $this->member('specialist_support', ['planning_circumstances' => ['breastfeeding']]);
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_context' => 'unsure'])->assertSessionHasNoErrors();
        $this->assertNull($user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame('review', app(Onboarding::class)->state($user)['targets']['status']);

        $this->put(route('nutrition-profile.update'), ['planning_context' => 'general_adult'])->assertSessionHasNoErrors();
        $this->assertSame('ready', app(Onboarding::class)->state($user)['targets']['status']);
        $this->put(route('nutrition-profile.update'), ['planning_context' => 'specialist_support'])->assertSessionHasNoErrors();
        $this->assertSame('review', app(Onboarding::class)->state($user)['targets']['status']);
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
    }

    public function test_no_radio_answer_can_replace_a_previous_empty_checkbox_submission(): void
    {
        $user = $this->member(null, ['planning_circumstances' => []]);
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_context' => 'general_adult'])->assertSessionHasNoErrors();
        $this->assertNull($user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame('ready', app(Onboarding::class)->state($user)['targets']['status']);
    }

    public function test_validation_errors_keep_the_new_radio_answer_instead_of_the_saved_one(): void
    {
        $user = $this->member('general_adult');
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), ['planning_context' => 'unsure', 'height_cm' => 1])
            ->assertSessionHasErrors('height_cm')->assertSessionHasInput('planning_context', 'unsure');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame(['unsure'], $this->selectedAnswers($response->getContent()));
        $this->assertSame('general_adult', $user->nutritionProfile()->first()->planning_context);
    }

    public function test_multiple_radio_answers_are_rejected_server_side(): void
    {
        $user = $this->member('unsure');
        $this->actingAs($user)->putJson(route('nutrition-profile.update'), ['planning_context' => ['general_adult', 'specialist_support']])
            ->assertUnprocessable()->assertJsonValidationErrors('planning_context');
        $this->assertSame('unsure', $user->nutritionProfile()->first()->planning_context);
    }
}
