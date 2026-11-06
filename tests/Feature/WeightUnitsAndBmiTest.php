<?php

namespace Tests\Feature;

use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\WeeklyMealPlanner;
use App\Services\Nutrition\BodyMassIndex;
use App\Services\Nutrition\NutritionTargetCalculator;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeightUnitsAndBmiTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $overrides = []): User
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create(array_replace([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80, 'target_weight_kg' => 75,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false, 'meals_per_day' => 3, 'snacks_per_day' => 0,
        ], $overrides));

        return $user;
    }

    private function input(TestResponse $response, string $name): string
    {
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());

        return (new DOMXPath($document))->query('//input[@name="'.$name.'"]')->item(0)->getAttribute('value');
    }

    public function test_profile_and_target_pounds_are_saved_as_precise_kilograms_and_displayed_in_pounds(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['weight_lbs' => 180, 'target_weight_lbs' => 170])->assertSessionHasNoErrors();
        $profile = $user->nutritionProfile()->first();
        $this->assertSame('81.646626600', $profile->weight_kg);
        $this->assertSame('77.110702900', $profile->target_weight_kg);
        $response = $this->get(route('nutrition-profile.edit'))->assertOk()->assertSee('180.0 lbs')->assertSee('170.0 lbs');
        $this->assertSame('180.0', $this->input($response, 'weight_lbs'));
        $this->assertSame('170.0', $this->input($response, 'target_weight_lbs'));
    }

    public function test_existing_kilograms_do_not_change_on_display_or_unchanged_rounded_form_submission(): void
    {
        $user = $this->member(['weight_kg' => '80.123456789']);
        $before = $user->nutritionProfile()->first()->getAttributes();
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame('176.6', $this->input($response, 'weight_lbs'));
        $this->assertSame('165.3', $this->input($response, 'target_weight_lbs'));
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
        $this->put(route('nutrition-profile.update'), ['weight_lbs' => '176.6', 'target_weight_lbs' => '165.3'])->assertSessionHasNoErrors();
        $this->assertSame('80.123456789', $user->nutritionProfile()->first()->weight_kg);
        $this->assertSame('75.000000000', $user->nutritionProfile()->first()->target_weight_kg);
    }

    public function test_lbs_validation_round_trip_preserves_original_input_without_double_conversion(): void
    {
        $user = $this->member();
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), ['weight_lbs' => '185.3', 'target_weight_lbs' => '170.2', 'planning_context' => 'invalid'])->assertSessionHasErrors('planning_context');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame('185.3', $this->input($response, 'weight_lbs'));
        $this->assertSame('170.2', $this->input($response, 'target_weight_lbs'));
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
    }

    public function test_blank_target_stays_blank_after_validation_errors_and_can_be_cleared(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['target_weight_lbs' => null, 'goal' => 'invalid'])->assertSessionHasErrors('goal');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame('', $this->input($response, 'target_weight_lbs'));
        $this->assertSame('75.000000000', $user->nutritionProfile()->first()->target_weight_kg);
        $this->put(route('nutrition-profile.update'), ['target_weight_lbs' => null])->assertSessionHasNoErrors();
        $this->assertNull($user->nutritionProfile()->first()->target_weight_kg);
    }

    public static function invalidPounds(): array
    {
        return ['below equivalent kg minimum' => [44, 'between'], 'above equivalent kg maximum' => [1102.4, 'between'], 'not numeric' => ['invalid', 'number'], 'array' => [[180], 'number']];
    }

    #[DataProvider('invalidPounds')]
    public function test_weight_limits_are_validated_in_pounds_without_changing_saved_data(mixed $value, string $message): void
    {
        $user = $this->member();
        $response = $this->actingAs($user)->put(route('nutrition-profile.update'), ['weight_lbs' => $value, 'target_weight_lbs' => $value])->assertSessionHasErrors(['weight_lbs', 'target_weight_lbs']);
        $errors = session('errors')->all();
        $this->assertStringContainsString($message, implode(' ', $errors));
        $this->assertStringContainsString('lbs', implode(' ', $errors));
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
        $this->assertSame('75.000000000', $user->nutritionProfile()->first()->target_weight_kg);
    }

    public function test_legacy_kg_fields_keep_their_units_and_mixed_unit_requests_are_rejected(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['weight_kg' => 82.55, 'target_weight_kg' => 78.12])->assertSessionHasNoErrors();
        $this->assertSame('82.550000000', $user->nutritionProfile()->first()->weight_kg);
        $this->assertSame('78.120000000', $user->nutritionProfile()->first()->target_weight_kg);
        $this->put(route('nutrition-profile.update'), ['weight_kg' => 90, 'weight_lbs' => 180])->assertSessionHasErrors('weight_lbs');
        $this->assertSame('82.550000000', $user->nutritionProfile()->first()->weight_kg);
        $this->post(route('progress.store'), ['recorded_on' => '2026-01-01', 'weight_kg' => 80.25])->assertSessionHasNoErrors();
        $this->assertSame('80.250000000', $user->weightEntries()->first()->weight_kg);
    }

    public function test_recording_and_editing_pounds_do_not_update_profile_or_nutrition_targets(): void
    {
        $user = $this->member();
        $profile = $user->nutritionProfile()->first();
        $before = $profile->getAttributes();
        $targets = app(NutritionTargetCalculator::class)->calculate($profile);
        $this->actingAs($user)->post(route('progress.store'), ['recorded_on' => '2026-01-01', 'weight_lbs' => 180])->assertSessionHasNoErrors();
        $entry = $user->weightEntries()->firstOrFail();
        $this->assertSame('81.646626600', $entry->weight_kg);
        $response = $this->get(route('progress.index', ['edit' => $entry->id]))->assertOk()->assertSee('180.0 lbs');
        $this->assertSame('180.0', $this->input($response, 'weight_lbs'));
        $this->put(route('progress.update', $entry->id), ['recorded_on' => '2026-01-01', 'weight_lbs' => 181])->assertSessionHasNoErrors();
        $this->assertSame('82.100218970', $entry->fresh()->weight_kg);
        $this->assertSame($before, $profile->fresh()->getAttributes());
        $this->assertSame($targets, app(NutritionTargetCalculator::class)->calculate($profile->fresh()));
    }

    public function test_progress_validation_errors_preserve_pounds_when_editing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'Europe/London'));
        $user = $this->member();
        $entry = $user->weightEntries()->create(['recorded_on' => '2026-11-01', 'weight_kg' => '80.123456789']);
        $editUrl = route('progress.index', ['edit' => $entry->id]);
        $this->actingAs($user)->from($editUrl)->put(route('progress.update', $entry->id), ['recorded_on' => '2026-11-06', 'weight_lbs' => '190.4'])->assertSessionHasErrors('recorded_on');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($editUrl)->assertOk();
        $this->assertSame('190.4', $this->input($response, 'weight_lbs'));
        $this->assertSame('80.123456789', $entry->fresh()->weight_kg);
        $this->put(route('progress.update', $entry->id), ['recorded_on' => '2026-11-01', 'weight_lbs' => '176.6'])->assertSessionHasNoErrors();
        $this->assertSame('80.123456789', $entry->fresh()->weight_kg);
    }

    public function test_progress_still_enforces_ownership_daily_uniqueness_and_pound_bounds(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $entry = $other->weightEntries()->create(['recorded_on' => '2026-01-01', 'weight_kg' => 80]);
        $this->actingAs($owner)->put(route('progress.update', $entry->id), ['recorded_on' => '2026-01-01', 'weight_lbs' => 180])->assertNotFound();
        $this->post(route('progress.store'), ['recorded_on' => '2026-01-01', 'weight_lbs' => 44])->assertSessionHasErrors('weight_lbs');
        $this->post(route('progress.store'), ['recorded_on' => '2026-01-01', 'weight_lbs' => 180])->assertSessionHasNoErrors();
        $this->post(route('progress.store'), ['recorded_on' => '2026-01-01', 'weight_lbs' => 181])->assertSessionHasErrors('recorded_on');
        $this->assertSame('80.000000000', $entry->fresh()->weight_kg);
        $this->assertSame(1, $owner->weightEntries()->count());
    }

    public function test_bmi_uses_profile_weight_on_profile_and_latest_non_future_dated_entry_on_progress(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'Europe/London'));
        $user = $this->member();
        $user->weightEntries()->create(['recorded_on' => '2026-11-04', 'weight_kg' => 90]);
        $user->weightEntries()->create(['recorded_on' => '2026-11-06', 'weight_kg' => 200]);
        $user->weightEntries()->create(['recorded_on' => '2026-11-01', 'weight_kg' => 85]);
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertSee('BMI · profile weight')->assertSee('24.7')->assertSee('profile weight')->assertSee('176.4 lbs');
        $response = $this->get(route('progress.index'))->assertOk()->assertSee('BMI: 27.8')->assertSee('recorded 4 Nov 2026')->assertSee('198.4 lbs')->assertSee('Weight in pounds (lbs)');
        $this->assertEqualsWithDelta(27.7777777778, $response->viewData('bmi')['value'], 0.000000001);
        $points = $response->viewData('chartPoints');
        $this->assertCount(2, $points);
        $this->assertEqualsWithDelta(187.39292285714592, $points[0]['weight'], 0.000000001);
        $this->assertEqualsWithDelta(198.41603596638983, $points[1]['weight'], 0.000000001);
        $this->assertSame('2026-11-04', $response->viewData('latestEntry')->recorded_on->toDateString());
        $other = $this->member();
        $other->weightEntries()->create(['recorded_on' => '2026-11-05', 'weight_kg' => 150]);
        $this->assertSame('2026-11-04', app(BodyMassIndex::class)->forProgress($user)['date']);
    }

    public function test_bmi_falls_back_to_profile_when_only_future_entries_exist_and_dashboard_labels_the_source(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'Europe/London'));
        $user = $this->member();
        $user->weightEntries()->create(['recorded_on' => '2026-11-06', 'weight_kg' => 200]);
        $response = $this->actingAs($user)->get(route('progress.index'))->assertOk()->assertSee('BMI: 24.7')->assertSee('profile last saved 5 Nov 2026');
        $this->assertSame('profile', $response->viewData('bmi')['source']);
        MealPlanEntry::create(['user_id' => $user->id, 'plan_date' => '2026-11-05', 'slot' => 'lunch', 'snapshot' => ['name' => 'Saved lunch']]);
        $this->get(route('dashboard'))->assertOk()->assertSee('Latest BMI')->assertSee('24.7')->assertSee('176.4 lbs')->assertDontSee('440.9');
    }

    public static function invalidMeasurements(): array
    {
        return ['missing height' => [80, null], 'missing weight' => [null, 180], 'zero height' => [80, 0], 'negative height' => [80, -180], 'zero weight' => [0, 180], 'negative weight' => [-80, 180], 'non numeric' => ['invalid', 180], 'infinite' => [INF, 180], 'height below profile limit' => [80, 49], 'height above profile limit' => [80, 301], 'weight below profile limit' => [19, 180], 'weight above profile limit' => [501, 180]];
    }

    #[DataProvider('invalidMeasurements')]
    public function test_missing_or_invalid_measurements_have_no_bmi(mixed $weight, mixed $height): void
    {
        $this->assertNull(app(BodyMassIndex::class)->calculate($weight, $height));
    }

    public function test_missing_measurements_render_a_helpful_bmi_empty_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertSee('BMI · profile weight')->assertSee('Not available')->assertSee('Add a valid height')->assertSee('not a diagnosis or a measure of body fat');
    }

    public function test_equivalent_lbs_and_kilogram_profiles_have_identical_nutrition_calculations(): void
    {
        $user = $this->member();
        $before = app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first());
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['weight_lbs' => '176.36980974790206'])->assertSessionHasNoErrors();
        $this->assertSame($before, app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first()));
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
    }

    public function test_precision_migration_preserves_existing_kg_values_without_converting_records(): void
    {
        $user = $this->member();
        $entry = $user->weightEntries()->create(['recorded_on' => '2026-01-01', 'weight_kg' => 70.12]);
        $file = glob(database_path('migrations/*_increase_weight_precision.php'))[0];
        $migration = require $file;
        $migration->down();
        $migration->up();
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
        $this->assertSame('75.000000000', $user->nutritionProfile()->first()->target_weight_kg);
        $this->assertSame('70.120000000', $entry->fresh()->weight_kg);
    }

    public function test_today_entry_is_included_in_chart_and_bmi_without_mutating_the_profile(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 23:30:00', 'Europe/London'));
        $user = $this->member();
        $this->actingAs($user)->post(route('progress.store'), ['recorded_on' => '2026-11-05', 'weight_lbs' => 180])->assertSessionHasNoErrors();
        $response = $this->get(route('progress.index'))->assertOk()->assertSee('180.0 lbs')->assertSee('recorded 5 Nov 2026');
        $this->assertCount(1, $response->viewData('chartPoints'));
        $this->assertSame('2026-11-05', $response->viewData('chartPoints')[0]['date']);
        $this->assertSame('entry', $response->viewData('bmi')['source']);
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
    }

    public function test_equivalent_kg_limits_are_preserved_for_pound_submissions(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['weight_lbs' => '44.09245243697551', 'target_weight_lbs' => '1102.3113109243878'])->assertSessionHasNoErrors();
        $this->assertSame('20.000000000', $user->nutritionProfile()->first()->weight_kg);
        $this->assertSame('500.000000000', $user->nutritionProfile()->first()->target_weight_kg);
    }

    public function test_bmi_remains_visible_for_review_required_profiles_without_changing_eligibility(): void
    {
        $user = $this->member(['weight_kg' => 100]);
        $before = app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first());
        $this->assertSame('review', $before['status']);
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertSee('BMI · profile weight')->assertSee('30.9');
        $this->get(route('weekly-meals.create'))->assertOk()->assertSee('Meal generation unavailable');
        $this->assertSame($before, app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first()));
    }

    public function test_administration_customer_summary_uses_pounds_without_writing_weights(): void
    {
        $user = $this->member();
        $entry = $user->weightEntries()->create(['recorded_on' => '2026-01-01', 'weight_kg' => 90]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.users.show', $user))->assertOk()->assertSee('176.4')->assertSee('198.4')->assertSee('165.3')->assertSee('lbs')->assertDontSee('<small>kg</small>', false);
        $this->assertSame('90.000000000', $entry->fresh()->weight_kg);
        $this->assertSame('80.000000000', $user->nutritionProfile()->first()->weight_kg);
    }

    public function test_precision_padding_does_not_invalidate_existing_weekly_previews_but_real_weight_changes_do(): void
    {
        $member = $this->member();
        $profile = $member->nutritionProfile()->first();
        $attributes = $profile->getAttributes();
        $attributes['weight_kg'] = '80.00';
        $attributes['target_weight_kg'] = '75.00';
        $profile->setRawAttributes($attributes, true);
        $relation = \Mockery::mock(HasOne::class);
        $relation->shouldReceive('first')->andReturn($profile);
        $user = \Mockery::mock(User::class);
        $user->shouldReceive('nutritionProfile')->andReturn($relation);
        $this->mock(MealIdeaGenerator::class, function ($mock) use ($user) {
            $mock->shouldReceive('context')->with($user, 'lunch')->andReturn(['signature' => 'unchanged-calculation-and-foods', 'meal_count' => 3, 'snack_count' => 0]);
        });
        $planner = app(WeeklyMealPlanner::class);
        $legacy = $planner->source($user);
        $draft = new WeeklyMealDraft(['source_signature' => $legacy['signature']]);
        $attributes['weight_kg'] = '80.000000000';
        $attributes['target_weight_kg'] = '75.000000000';
        $profile->setRawAttributes($attributes, true);
        $this->assertSame($legacy['signature'], $planner->assertCurrent($draft, $user)['signature']);
        $attributes['weight_kg'] = '80.000000001';
        $profile->setRawAttributes($attributes, true);
        $this->assertNotSame($legacy['signature'], $planner->source($user)['signature']);
    }

    public function test_bmi_is_inside_the_profile_and_dashboard_cards_once_with_source_and_screening_guidance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'Europe/London'));
        $user = $this->member();
        $user->weightEntries()->create(['recorded_on' => '2026-11-04', 'weight_kg' => 90]);
        $user->weightEntries()->create(['recorded_on' => '2026-11-06', 'weight_kg' => 200]);
        MealPlanEntry::create(['user_id' => $user->id, 'plan_date' => '2026-11-05', 'slot' => 'lunch', 'snapshot' => ['name' => 'Saved lunch']]);
        $this->actingAs($user);
        foreach (['nutrition-profile.edit' => ['personal-heading', '24.7', 'profile weight', 'profile last saved 5 Nov 2026'], 'dashboard' => ['dn-progress-title', '27.8', 'your weigh-in of', 'recorded 4 Nov 2026']] as $route => [$heading, $value, $source, $date]) {
            $response = $this->get(route($route))->assertOk();
            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//*[@data-bmi-value]')->length);
            $card = $xpath->query('//section[@aria-labelledby="'.$heading.'"]')->item(0);
            $display = $xpath->query('.//*[@data-bmi-value]', $card);
            $this->assertSame(1, $display->length);
            $this->assertSame($value, trim($display->item(0)->textContent));
            $text = preg_replace('/\s+/u', ' ', $card->textContent);
            $this->assertStringContainsString($source, $text);
            $this->assertStringContainsString($date, $text);
            $this->assertStringContainsString('not a diagnosis or a measure of body fat', $text);
            $this->assertSame(0, $xpath->query('//aside[@aria-label="Body mass index"]')->length);
            $this->assertSame(0, $xpath->query('//input[contains(@name,"bmi")]')->length);
        }
    }

    public function test_onboarding_bmi_empty_state_is_inside_personal_details_without_an_extra_summary_grid(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('nutrition-profile.edit'))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//section[@aria-labelledby="personal-heading"]//*[@data-bmi-value]')->length);
        $this->assertSame('Not available', trim($xpath->query('//*[@data-bmi-value]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//*[@id="nutrition-profile-form"]//dl')->length);
        $this->assertSame(0, $xpath->query('//aside[@aria-label="Body mass index"]')->length);
        $response->assertSee('Add a valid height')->assertSee('not a diagnosis or a measure of body fat');
    }
}
