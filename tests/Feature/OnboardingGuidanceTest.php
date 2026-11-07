<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\WeeklyMealPlanner;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnboardingGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $profileChanges = []): User
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create(array_replace([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ], $profileChanges));

        return $user;
    }

    public static function noticePages(): array
    {
        return ['profile' => ['nutrition-profile.edit'], 'preferences' => ['food-preferences.edit'], 'weekly generation' => ['weekly-meals.create'], 'weekly preview' => ['weekly-meals.show']];
    }

    #[DataProvider('noticePages')]
    public function test_allergy_notice_is_visible_once_and_estimates_have_a_consistent_disclaimer(string $page): void
    {
        $user = $this->member();
        $parameters = [];
        if (str_starts_with($page, 'weekly-meals.')) {
            foreach (['Rice', 'Carrot', 'Chicken'] as $name) {
                Ingredient::create(['food_code' => 'GUIDANCE-'.$name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                    'energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10, 'sugars_g' => 0, 'fat_g' => 2, 'saturates_g' => 0, 'fibre_g' => 2, 'salt_g' => 0]);
            }
            if ($page === 'weekly-meals.show') {
                $source = app(WeeklyMealPlanner::class)->source($user);
                $draft = WeeklyMealDraft::create(['user_id' => $user->id, 'start_date' => '2026-11-09', 'status' => 'ready', 'source_signature' => $source['signature'], 'meal_count' => 3, 'slots' => $source['slots'], 'mode' => 'fill', 'proposals' => []]);
                $parameters = [$draft->id];
            }
        }
        $response = $this->actingAs($user)->get(route($page, $parameters))->assertOk()
            ->assertSee('Marking a food as disliked or excluded does not make a plan allergy-safe.')
            ->assertSee('If you have a food allergy, do not rely on generated meals to manage it.');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $notice = $xpath->query('//aside[@aria-label="Important: food allergies"]');
        $this->assertSame(1, $notice->length);
        $this->assertSame(0, $xpath->query('ancestor::details', $notice->item(0))->length);
        if ($page !== 'food-preferences.edit') {
            $this->assertSame(1, $xpath->query('//p[@class="guidance-estimate"]')->length);
            $response->assertSee('They are a starting point for meal planning, rather than a medical assessment or a guarantee of results.');
        } else {
            $response->assertSee('You can update them at any time.')->assertSee('even if you leave every food Unspecified.')->assertSee('Existing saved meals and plans are not changed.');
        }
    }

    public function test_incomplete_profile_uses_setup_instructions_and_keeps_missing_details_visible(): void
    {
        $user = $this->member(['height_cm' => null]);
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Tell us about yourself, your goals and how many meals')->assertSee('Complete: height.')
            ->assertSee('Optional fields can be left blank.')->assertDontSee('Automatic planning unavailable')
            ->assertSee('Optional. Add a weight goal you have chosen for yourself. DietsDNA does not assess whether this goal is suitable for you.');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//div[@class="setup-note"]')->length);
        $this->assertSame(0, $xpath->query('//div[contains(@class,"setup-review")]')->length);
    }

    public function test_review_reasons_are_distinct_from_missing_details_and_are_not_duplicated_on_weekly_generation(): void
    {
        $user = $this->member(['planning_context' => 'needs_review', 'weight_kg' => 100]);
        $this->actingAs($user)->get(route('weekly-meals.create'))->assertOk()
            ->assertSee('Your setup is complete. Automatic meal generation is currently unavailable.')
            ->assertSee('Keep your answers and food choices accurate; finishing setup does not mean meals can always be generated.');
        $response = $this->get(route('nutrition-profile.edit'))->assertOk()->assertDontSee('Tell us about yourself, your goals and how many meals');
        $this->assertSame(1, substr_count($response->getContent(), 'Your measurements fall outside the range supported by automatic planning'));
        $response = $this->get(route('weekly-meals.create'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Your measurements fall outside the range supported by automatic planning'));
        $this->assertStringNotContainsString('id="weekly-start-form"', $response->getContent());
    }

    public function test_preferences_saved_confirmation_is_displayed_once(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('food-preferences.update'), [])->assertSessionHasNoErrors();
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('food-preferences.edit'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Your food preferences have been saved.'));
    }

    public function test_onboarding_dashboard_uses_personal_guidance_without_technical_calculator_language(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
            ->assertSee('Add your measurements, activity level and goals to help us personalise your meal plan.')
            ->assertSee('rather than a medical assessment or a guarantee of results.')
            ->assertDontSee('existing nutrition calculator');
    }

    public static function progressPages(): array
    {
        return ['dashboard' => ['dashboard', 1], 'nutrition profile' => ['nutrition-profile.edit', 1], 'food preferences' => ['food-preferences.edit', 1], 'weekly preview' => ['weekly-meals.create', 3]];
    }

    #[DataProvider('progressPages')]
    public function test_progress_subtitles_are_consistent_across_pages_and_keep_step_states(string $page, int $currentStep): void
    {
        $user = $currentStep === 3 ? $this->member() : User::factory()->create();
        $response = $this->actingAs($user)->get(route($page))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $progress = '//nav[@aria-label="Getting started"]/ol';
        $subtitles = $xpath->query($progress.'//small[@class="setup-progress-subtitle"]');
        $this->assertSame(['Your details, goals and meal pattern', 'Foods you enjoy and prefer to avoid', 'Optional once setup is complete'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($subtitles)));
        $this->assertSame(1, $xpath->query($progress.'/li[@aria-current="step"]')->length);
        $this->assertSame(1, $xpath->query($progress.'/li['.$currentStep.'][@aria-current="step"]')->length);
        $this->assertSame(0, $xpath->query($progress.'//a')->length);
        $statuses = $xpath->query($progress.'//small[@class="setup-progress-status"]');
        $this->assertSame($currentStep === 3 ? ['Details saved', 'Reviewed and saved'] : [], array_map(fn ($node) => trim($node->textContent), iterator_to_array($statuses)));
    }
}
