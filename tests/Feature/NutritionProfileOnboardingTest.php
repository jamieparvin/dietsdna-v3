<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NutritionProfileOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function profile(User $user, array $changes = []): void
    {
        $user->nutritionProfile()->create(array_replace([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ], $changes));
    }

    private function document(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());

        return new DOMXPath($document);
    }

    public function test_first_time_profile_shows_ordered_editable_sections_and_one_save_action_without_empty_summaries(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Profile not started')->assertSee('Getting started')->assertSee('Optional fields can be left blank.')
            ->assertDontSee('Ready to personalise')->assertDontSee('Not set');
        $xpath = $this->document($response);
        $form = '//*[@id="nutrition-profile-form"]';
        $headings = $xpath->query($form.'//section//h2');
        $this->assertSame(['Personal details', 'Goals and activity', 'Meal options', 'Calculation details'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($headings)));
        $this->assertSame(0, $xpath->query($form.'//dl | '.$form.'//details')->length);
        $buttons = $xpath->query('//*[contains(@class,"pf-page")]//button[@type="submit"]');
        $this->assertSame(1, $buttons->length);
        $this->assertSame('Save and continue', trim($buttons->item(0)->textContent));
        foreach (['date_of_birth', 'height_cm', 'weight_lbs', 'goal', 'activity_level', 'meals_per_day', 'calculation_sex', 'resistance_training'] as $field) {
            $this->assertStringContainsString('Required for setup', $xpath->query($form.'//label[@for="'.$field.'"]')->item(0)->textContent);
            $this->assertSame(1, $xpath->query($form.'//*[@name="'.$field.'"]')->length);
            $this->assertSame(0, $xpath->query($form.'//*[@name="'.$field.'" and @required]')->length);
        }
        $this->assertSame(0, $xpath->query($form.'//*[@name="planning_context"]')->length);
        foreach (['target_weight_lbs', 'cooking_minutes', 'snacks_per_day'] as $field) {
            $this->assertStringContainsString('(Optional)', $xpath->query($form.'//label[@for="'.$field.'"]')->item(0)->textContent);
        }
        foreach (['meal-ideas.index', 'saved-meals.index', 'meal-plan.index', 'shopping-list.index', 'progress.index'] as $route) {
            $this->assertSame(0, $xpath->query('//a[@href="'.route($route).'"]')->length);
        }
    }

    public function test_partial_save_redisplays_saved_fields_and_incomplete_guidance_without_summary_cards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk();
        $this->put(route('nutrition-profile.update'), ['height_cm' => 180, 'weight_kg' => 81])->assertSessionHasNoErrors()->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertRedirect(route('nutrition-profile.edit'));
        $response = $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Profile setup in progress')->assertSee('Complete:')->assertSee('date of birth');
        $xpath = $this->document($response);
        $this->assertSame('178.6', $xpath->query('//*[@id="weight_lbs"]')->item(0)->getAttribute('value'));
        $this->assertSame('180.00', $xpath->query('//*[@id="height_cm"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//*[@id="nutrition-profile-form"]//dl')->length);
        $this->assertDatabaseHas('nutrition_profiles', ['user_id' => $user->id, 'height_cm' => 180, 'weight_kg' => 81, 'goal' => null]);
    }

    public function test_onboarding_validation_errors_leave_fields_visible_and_keep_attempted_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), ['weight_kg' => 10, 'calculation_sex' => 'invalid'])
            ->assertSessionHasErrors(['weight_kg', 'calculation_sex'])->assertRedirect(route('nutrition-profile.edit'));
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk()->assertSee('The weight kg field must be between 20 and 500.');
        $xpath = $this->document($response);
        $this->assertSame('22.0', $xpath->query('//*[@id="weight_lbs"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//*[@id="nutrition-profile-form"]//details')->length);
        $this->assertDatabaseCount('nutrition_profiles', 0);
    }

    public function test_review_required_details_keep_the_editable_form_and_truthful_saved_answers_during_setup(): void
    {
        $user = User::factory()->create();
        $this->profile($user, ['planning_context' => 'needs_review', 'weight_kg' => 100]);
        $before = $user->nutritionProfile()->first()->getAttributes();
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Details saved - automatic planning unavailable')->assertSee('Your measurements fall outside the range supported by automatic planning')->assertSee('Keep your answers accurate');
        $xpath = $this->document($response);
        $this->assertSame(0, $xpath->query('//*[@id="planning_context"]')->length);
        $response->assertDontSee('Do you need specialist nutrition support?');
        $this->assertSame(0, $xpath->query('//*[@id="nutrition-profile-form"]//dl')->length);
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
    }

    public function test_completed_setup_retains_summaries_collapsed_editors_and_macro_controls(): void
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->profile($user);
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Saved')->assertSee('Edit personal details')->assertSee('Your macro targets')->assertDontSee('Save and continue');
        $xpath = $this->document($response);
        $this->assertSame(4, $xpath->query('//*[@id="nutrition-profile-form"]//dl')->length);
        $this->assertSame(3, $xpath->query('//*[@id="nutrition-profile-form"]//details[not(@open)]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="macro-preferences-form"]')->length);
    }

    public static function invalidSectionFields(): array
    {
        return ['personal' => ['height_cm', 10], 'goals' => ['goal', 'invalid'], 'meal pattern' => ['meals_per_day', 1], 'calculation' => ['calculation_sex', 'invalid']];
    }

    #[DataProvider('invalidSectionFields')]
    public function test_returning_user_editor_containing_validation_errors_opens_automatically(string $field, mixed $value): void
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->profile($user);
        $before = $user->nutritionProfile()->first()->getAttributes();
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), [$field => $value])
            ->assertSessionHasErrors($field)->assertRedirect(route('nutrition-profile.edit'));
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $xpath = $this->document($response);
        $this->assertSame(1, $xpath->query('//*[@id="'.$field.'"]/ancestor::details[@open]')->length);
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
    }
}
