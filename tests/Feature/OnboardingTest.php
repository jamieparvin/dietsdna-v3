<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSetupComplete;
use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnboardingTest extends TestCase
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

    public function test_registration_enters_profile_setup_even_with_an_intended_url(): void
    {
        Notification::fake();
        $this->withSession(['url.intended' => route('meal-plan.index')])->post('/register', [
            'name' => 'New member', 'email' => 'new@example.com',
            'password' => 'Secure-password-123!', 'password_confirmation' => 'Secure-password-123!',
        ])->assertRedirect(route('nutrition-profile.edit'));
        $this->assertAuthenticated();
        $this->assertNull(User::where('email', 'new@example.com')->firstOrFail()->food_preferences_reviewed_at);
    }

    public function test_dashboard_guides_new_user_without_empty_metrics_or_generation_forms(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
            ->assertSee('Continue to nutrition profile')->assertSee('Getting started')
            ->assertDontSee('Today’s menu')->assertDontSee('Generate my week');
        $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('Save and continue');
    }

    public function test_partial_profiles_resume_profile_and_saves_preserve_restrictions(): void
    {
        $user = User::factory()->create();
        $this->profile($user, ['height_cm' => null, 'allergies' => 'Recorded allergy', 'foods_to_avoid' => 'Recorded restriction']);
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['weight_kg' => 81])
            ->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertRedirect(route('nutrition-profile.edit'));
        $this->assertDatabaseHas('nutrition_profiles', ['user_id' => $user->id, 'height_cm' => null, 'allergies' => 'Recorded allergy', 'foods_to_avoid' => 'Recorded restriction']);
    }

    public function test_returning_login_resumes_preferences_and_all_unspecified_save_finishes_setup(): void
    {
        $user = User::factory()->create();
        $this->profile($user);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('food-preferences.edit'));
        $this->get(route('food-preferences.edit'))->assertOk()->assertSee('Save and continue');
        $this->put(route('food-preferences.update'), [])->assertRedirect(route('setup'));
        $this->assertNotNull($user->fresh()->food_preferences_reviewed_at);
        $this->assertDatabaseCount('user_food_preferences', 0);
        $this->assertDatabaseCount('weekly_meal_drafts', 0);
        $this->get(route('setup'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Your setup is complete');
    }

    public function test_setup_completion_is_independent_of_ingredient_eligibility_and_never_queues_a_blocked_preview(): void
    {
        Http::preventStrayRequests();
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->profile($user);
        $this->actingAs($user)->get(route('weekly-meals.create'))->assertOk()
            ->assertSee('not enough eligible ingredients')->assertSee('setup is complete');
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertSessionHasErrors();
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        $this->assertDatabaseCount('weekly_meal_drafts', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_review_profiles_can_finish_setup_without_changing_truthful_answers(): void
    {
        $user = User::factory()->create();
        $this->profile($user, ['planning_context' => 'needs_review', 'weight_kg' => 100]);
        $this->actingAs($user)->get(route('setup'))->assertRedirect(route('food-preferences.edit'));
        $this->get(route('food-preferences.edit'))->assertOk()->assertSee('Keep your answers accurate');
        $this->put(route('food-preferences.update'), [])->assertRedirect(route('setup'));
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee('Your measurements fall outside the range supported by automatic planning');
        $this->assertTrue(app(Onboarding::class)->state($user->fresh())['complete']);
        $this->assertDatabaseHas('nutrition_profiles', ['user_id' => $user->id, 'planning_context' => 'needs_review']);
    }

    public function test_all_dependent_routes_reject_incomplete_users_including_direct_write_requests(): void
    {
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $this->actingAs(User::factory()->create());
        $routes = app('router')->getRoutes();
        $checked = 0;
        foreach ($routes as $route) {
            if (! in_array(EnsureSetupComplete::class, $route->gatherMiddleware(), true)) {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '999', $route->uri());
            $this->call($route->methods()[0], '/'.$uri)->assertRedirect(route('nutrition-profile.edit'));
            $checked++;
        }
        $this->assertGreaterThan(20, $checked);
        $this->postJson(route('weekly-meals.store'), [])->assertForbidden()->assertJsonPath('setup_url', route('nutrition-profile.edit'));
        $this->assertDatabaseCount('weekly_meal_drafts', 0);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        Queue::assertNothingPushed();
    }

    public function test_saved_profile_still_requires_explicit_preferences_and_invalid_preferences_do_not_complete_setup(): void
    {
        $user = User::factory()->create();
        $this->profile($user);
        $this->actingAs($user)->post(route('weekly-meals.store'), [])->assertRedirect(route('food-preferences.edit'));
        $this->put(route('food-preferences.update'), ['preferences' => ['invalid' => 'excluded']])->assertSessionHasErrors();
        $this->assertNull($user->fresh()->food_preferences_reviewed_at);
    }

    public function test_backfill_only_marks_users_with_persisted_choices_and_preserves_data(): void
    {
        $existing = User::factory()->create();
        $unknown = User::factory()->create();
        $this->profile($existing);
        DB::table('user_food_preferences')->insert(['user_id' => $existing->id, 'food_key' => str_repeat('a', 64), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $profileBefore = $existing->nutritionProfile()->first()->getAttributes();
        $migration = require database_path('migrations/2026_11_05_192026_add_food_preferences_reviewed_at_to_users_table.php');
        $migration->down();
        $migration->up();
        $this->assertNotNull($existing->fresh()->food_preferences_reviewed_at);
        $this->assertNull($unknown->fresh()->food_preferences_reviewed_at);
        $this->assertSame($profileBefore, $existing->nutritionProfile()->first()->getAttributes());
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $existing->id, 'preference' => 'excluded']);
        $this->actingAs($existing->fresh())->get(route('food-preferences.edit'))->assertOk();
    }

    public function test_preferences_save_preserves_omitted_dislikes_and_neutral_choices_are_recorded_as_reviewed(): void
    {
        $user = User::factory()->create();
        $this->profile($user);
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => str_repeat('b', 64), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->put(route('food-preferences.update'), [])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $user->id, 'food_key' => str_repeat('b', 64), 'preference' => 'excluded']);
        $this->assertNotNull($user->fresh()->food_preferences_reviewed_at);
    }

    public function test_logout_admin_and_account_updates_remain_accessible_during_setup(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNotNull(session('auth.password_confirmed_at'));
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_saving_explicit_unspecified_choices_removes_old_like_and_records_review(): void
    {
        $user = User::factory()->create();
        $this->profile($user);
        $food = Ingredient::create(['food_code' => 'SETUP-FOOD', 'name' => 'Rice', 'category' => 'Grains', 'sub_category' => 'Test', 'preparation_state' => 'dry', 'is_active' => true]);
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $food->preferenceFoodKey(), 'preference' => 'preferred', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->put(route('food-preferences.update'), ['preferences' => [$food->preferenceFoodKey() => 'neutral']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('user_food_preferences', 0);
        $this->assertNotNull($user->fresh()->food_preferences_reviewed_at);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_completed_user_can_queue_first_preview_without_creating_or_replacing_saved_plans(): void
    {
        Http::preventStrayRequests();
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->profile($user);
        foreach (['Rice', 'Carrot', 'Chicken'] as $name) {
            Ingredient::create(['food_code' => 'SETUP-'.$name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                'energy_kcal' => 100, 'protein_g' => 10, 'carbohydrate_g' => 10, 'sugars_g' => 0, 'fat_g' => 2, 'saturates_g' => 0, 'fibre_g' => 2, 'salt_g' => 0]);
        }
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => hash('sha256', 'setup-snapshot'), 'snapshot' => ['name' => 'Existing saved meal']]);
        $entry = MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id, 'plan_date' => '2026-11-09', 'slot' => 'lunch', 'snapshot' => $saved->snapshot]);
        $before = $entry->fresh()->getAttributes();
        $this->actingAs($user)->post(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertRedirect();
        $this->assertDatabaseHas('weekly_meal_drafts', ['user_id' => $user->id, 'status' => 'queued']);
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('saved_meals', 1);
        $this->assertDatabaseCount('meal_plan_entries', 1);
        Queue::assertPushed(GenerateWeeklyMealDraft::class, 1);
        Http::assertNothingSent();
    }

    public function test_completed_users_keep_intended_destinations_and_can_edit_setup_afterwards(): void
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->profile($user);
        $this->withSession(['url.intended' => route('food-preferences.edit')])->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('food-preferences.edit'));
        $this->put(route('nutrition-profile.update'), ['weight_kg' => 81])->assertRedirect(route('nutrition-profile.edit'));
        $this->put(route('food-preferences.update'), [])->assertRedirect(route('food-preferences.edit'));
        $this->assertDatabaseHas('nutrition_profiles', ['user_id' => $user->id, 'weight_kg' => 81]);
    }

    public function test_first_preview_progress_is_resumable_from_the_dashboard_before_any_plan_is_confirmed(): void
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->profile($user);
        WeeklyMealDraft::create(['user_id' => $user->id, 'start_date' => '2026-11-09', 'status' => 'queued', 'source_signature' => str_repeat('a', 64), 'meal_count' => 3, 'slots' => ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner'], 'mode' => 'fill']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('View preview progress')->assertDontSee('Continue to weekly preview')->assertDontSee('Today’s menu');
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
    }

    public static function incompleteNavigationProfiles(): array
    {
        return ['new user' => ['missing'], 'partial profile' => ['partial'], 'preferences not reviewed' => ['complete']];
    }

    #[DataProvider('incompleteNavigationProfiles')]
    public function test_shared_desktop_and_mobile_navigation_keeps_locked_features_visible_without_activation(string $profileState): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        if ($profileState !== 'missing') {
            $this->profile($user, $profileState === 'partial' ? ['height_cm' => null] : []);
        }
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $navigation = '//*[@id="sidebar-navigation"]';
        $this->assertSame(1, $xpath->query($navigation.'/summary[normalize-space()="Navigation menu"]')->length);
        $items = $xpath->query($navigation.'//a[contains(concat(" ", normalize-space(@class), " "), " nav-link ")]');
        $this->assertSame(['Dashboard', 'Nutrition profile', 'Food preferences', 'Meal ideas', 'Saved meals', 'Meal plan', 'Shopping list', 'Progress', 'Administration'], array_map(fn ($node) => trim($node->firstChild->textContent), iterator_to_array($items)));
        foreach (['Dashboard' => 'dashboard', 'Nutrition profile' => 'nutrition-profile.edit', 'Food preferences' => 'food-preferences.edit', 'Administration' => 'admin.dashboard'] as $label => $route) {
            $item = $xpath->query($navigation.'//a[starts-with(normalize-space(),"'.$label.'")]')->item(0);
            $this->assertSame(route($route), $item->getAttribute('href'));
            $this->assertFalse($item->hasAttribute('aria-disabled'));
        }
        foreach (['Meal ideas', 'Saved meals', 'Meal plan', 'Shopping list', 'Progress'] as $label) {
            $item = $xpath->query($navigation.'//a[normalize-space()="'.$label.'"]')->item(0);
            $this->assertSame('true', $item->getAttribute('aria-disabled'));
            $this->assertStringContainsString('nav-disabled', $item->getAttribute('class'));
            $this->assertFalse($item->hasAttribute('href'));
            $this->assertFalse($item->hasAttribute('tabindex'));
            $this->assertFalse($item->hasAttribute('onclick'));
            $this->assertFalse($item->hasAttribute('onkeydown'));
            $this->assertSame('setup-navigation-help', $item->getAttribute('aria-describedby'));
            $this->assertSame(1, $xpath->query('.//svg[@aria-hidden="true" and @focusable="false"]', $item)->length);
        }
        $this->assertSame('Complete your nutrition profile and food preferences to unlock these features.', trim($xpath->query($navigation.'//*[@id="setup-navigation-help"]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//form[@action="'.route('logout').'"]//button[@type="submit"]')->length);
    }

    public function test_navigation_unlocks_after_preferences_are_saved_even_when_generation_requires_review(): void
    {
        $user = User::factory()->create();
        $this->profile($user, ['planning_context' => 'needs_review']);
        $this->actingAs($user)->put(route('food-preferences.update'), [])->assertSessionHasNoErrors();
        $response = $this->get(route('dashboard'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['Meal ideas' => 'meal-ideas.index', 'Saved meals' => 'saved-meals.index', 'Meal plan' => 'meal-plan.index', 'Shopping list' => 'shopping-list.index', 'Progress' => 'progress.index'] as $label => $route) {
            $item = $xpath->query('//*[@id="sidebar-navigation"]//a[normalize-space()="'.$label.'"]')->item(0);
            $this->assertSame(route($route), $item->getAttribute('href'));
            $this->assertFalse($item->hasAttribute('aria-disabled'));
            $this->assertStringNotContainsString('nav-disabled', $item->getAttribute('class'));
        }
        $this->assertSame(0, $xpath->query('//*[@id="setup-navigation-help"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="sidebar-navigation"]//svg[contains(@class,"nav-lock")]')->length);
    }
}
