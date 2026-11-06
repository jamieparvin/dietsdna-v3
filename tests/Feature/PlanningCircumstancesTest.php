<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Onboarding;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanningCircumstancesTest extends TestCase
{
    use RefreshDatabase;

    private function member(?string $context = null): User
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => $context, 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ]);

        return $user;
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    private function selections(string $html): array
    {
        return array_map(fn ($choice) => $choice->getAttribute('value'), iterator_to_array($this->document($html)->query('//fieldset[@id="planning_context"]//input[@checked]')));
    }

    public function test_new_users_have_an_accessible_unselected_group_with_three_clickable_radio_options(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Do you need specialist nutrition support?')->assertDontSee('Select all that apply.')
            ->assertSee('DietsDNA provides standard meal planning for adults. Pregnancy, breastfeeding and medical dietary requirements are not currently supported.');
        $xpath = $this->document($response->getContent());
        $group = '//fieldset[@id="planning_context"]';
        $this->assertSame([], $this->selections($response->getContent()));
        $this->assertSame(1, $xpath->query($group.'/legend')->length);
        $this->assertSame(0, $xpath->query('//select[@name="planning_context"]')->length);
        $this->assertSame(3, $xpath->query($group.'//input[@type="radio" and @name="planning_context"]')->length);
        $labels = [];
        foreach ($xpath->query($group.'//input[@type="radio"]') as $choice) {
            $label = $xpath->query($group.'//label[@for="'.$choice->getAttribute('id').'"]')->item(0);
            $this->assertNotNull($label);
            $labels[] = trim($label->textContent);
        }
        $this->assertSame(['No — none of these apply to me', 'Yes — one or more apply to me', 'I’m not sure'], $labels);
        $this->assertStringContainsString('planning-context-help', $xpath->query($group)->item(0)->getAttribute('aria-describedby'));
        $this->assertSame(0, $xpath->query('//input[@name="planning_circumstances[]"]')->length);
    }

    public static function validSelections(): array
    {
        $cases = ['none' => [['none'], 'general_adult', 'ready']];
        $choices = ['pregnant', 'breastfeeding', 'medical_dietary_needs', 'not_sure'];
        for ($mask = 1; $mask < 16; $mask++) {
            $selected = [];
            foreach ($choices as $index => $choice) {
                if ($mask & (1 << $index)) {
                    $selected[] = $choice;
                }
            }
            $cases[implode(' + ', $selected)] = [$selected, $selected === ['not_sure'] ? 'unsure' : 'specialist_support', 'review'];
        }

        return $cases;
    }

    #[DataProvider('validSelections')]
    public function test_all_valid_combinations_persist_and_keep_setup_separate_from_planning_eligibility(array $selections, string $context, string $status): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_circumstances_present' => 1, 'planning_circumstances' => $selections])
            ->assertSessionHasNoErrors();

        $profile = $user->nutritionProfile()->first();
        $this->assertSame($selections, $profile->planning_circumstances);
        $this->assertSame($context, $profile->planning_context);
        $this->assertSame($status, app(NutritionTargetCalculator::class)->calculate($profile)['status']);
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        $response = $this->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame([$context], $this->selections($response->getContent()));
        $response->assertDontSee('Previously marked as needing specialist support');
        $this->assertSame('80.000000000', $profile->weight_kg);
        $this->assertDatabaseCount('meal_plan_entries', 0);
        $this->assertDatabaseCount('saved_meals', 0);
    }

    public static function invalidSelections(): array
    {
        return [
            'none and pregnant' => [['none', 'pregnant']],
            'not sure and none' => [['not_sure', 'none']],
            'duplicate' => [['pregnant', 'pregnant']],
            'unknown' => [['approved']],
            'scalar' => ['none'],
            'null' => [null],
            'nested' => [[['none']]],
            'blank' => [['']],
            'associative' => [['choice' => 'none']],
        ];
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_or_conflicting_selections_are_rejected_without_changing_saved_answers(mixed $selections): void
    {
        $user = $this->member('needs_review');
        $before = $user->nutritionProfile()->first()->getAttributes();

        $this->actingAs($user)->putJson(route('nutrition-profile.update'), ['planning_circumstances' => $selections])
            ->assertUnprocessable();

        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
    }

    public function test_none_exclusivity_has_an_actionable_validation_message(): void
    {
        $this->actingAs($this->member())->putJson(route('nutrition-profile.update'), ['planning_circumstances' => ['none', 'breastfeeding']])
            ->assertUnprocessable()->assertJsonValidationErrors(['planning_circumstances' => 'Select None apply on its own, or select the circumstances that apply to you.']);
    }

    public function test_checked_selections_survive_other_field_errors_without_being_saved(): void
    {
        $user = $this->member('general_adult');
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), [
            'planning_circumstances_present' => 1, 'planning_circumstances' => ['pregnant', 'medical_dietary_needs'], 'height_cm' => 1,
        ])->assertSessionHasErrors('height_cm')->assertSessionHasInput('planning_circumstances', ['pregnant', 'medical_dietary_needs']);

        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame(['specialist_support'], $this->selections($response->getContent()));
        $this->assertNull($user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame('general_adult', $user->nutritionProfile()->first()->planning_context);
    }

    public function test_conflicting_selections_remain_visible_for_correction_and_open_the_editor(): void
    {
        $user = $this->member('general_adult');
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), ['planning_circumstances_present' => 1, 'planning_circumstances' => ['pregnant', 'none']])
            ->assertSessionHasErrors('planning_circumstances');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk()
            ->assertSee('Select None apply on its own, or select the circumstances that apply to you.');
        $this->assertSame(['specialist_support'], $this->selections($response->getContent()));
        $xpath = $this->document($response->getContent());
        $this->assertSame(1, $xpath->query('//details[@open][.//fieldset[@id="planning_context"]]')->length);
        $this->assertSame(3, $xpath->query('//fieldset[@id="planning_context"]//input[@aria-invalid="true"]')->length);
    }

    public static function legacyAnswers(): array
    {
        return ['adult' => ['general_adult', ['general_adult']], 'unsure' => ['unsure', ['unsure']], 'review' => ['needs_review', ['specialist_support']], 'combined yes' => ['specialist_support', ['specialist_support']]];
    }

    #[DataProvider('legacyAnswers')]
    public function test_legacy_records_are_not_backfilled_or_guessed_and_survive_unrelated_saves(string $context, array $selected): void
    {
        $user = $this->member($context);
        $before = $user->nutritionProfile()->first()->getAttributes();
        $response = $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame($selected, $this->selections($response->getContent()));
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
        $this->put(route('nutrition-profile.update'), ['height_cm' => 180])->assertSessionHasNoErrors();
        $this->assertSame($context, $user->nutritionProfile()->first()->planning_context);
        $this->assertNull($user->nutritionProfile()->first()->planning_circumstances);
        if (in_array($context, ['needs_review', 'specialist_support'], true)) {
            $response->assertSee('Yes — one or more apply to me');
            $this->put(route('nutrition-profile.update'), ['planning_circumstances_present' => 1])->assertSessionHasNoErrors();
            $this->assertSame($context, $user->nutritionProfile()->first()->planning_context);
            $this->assertSame('review', app(Onboarding::class)->state($user)['targets']['status']);
            $this->put(route('nutrition-profile.update'), ['planning_context' => ''])->assertSessionHasNoErrors();
            $this->assertSame($context, $user->nutritionProfile()->first()->planning_context);
        }
    }

    public function test_legacy_restriction_changes_only_after_an_explicit_new_selection(): void
    {
        $user = $this->member('needs_review');
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_circumstances' => ['breastfeeding', 'not_sure']])->assertSessionHasNoErrors();
        $this->assertSame(['breastfeeding', 'not_sure'], $user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame('review', app(Onboarding::class)->state($user)['targets']['status']);
        $this->get(route('nutrition-profile.edit'))->assertOk()->assertDontSee('Previously marked as needing specialist support');

        $this->put(route('nutrition-profile.update'), ['planning_circumstances' => ['none']])->assertSessionHasNoErrors();
        $this->assertSame(['none'], $user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame('ready', app(Onboarding::class)->state($user)['targets']['status']);
    }

    public function test_empty_selection_saves_progress_but_cannot_complete_setup_and_stays_empty_after_errors(): void
    {
        $user = $this->member();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_circumstances_present' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], $user->nutritionProfile()->first()->planning_circumstances);
        $this->assertFalse(app(Onboarding::class)->state($user)['complete']);
        $this->get(route('weekly-meals.create'))->assertRedirect(route('nutrition-profile.edit'));
        $this->post(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertRedirect(route('nutrition-profile.edit'));

        $user = $this->member('general_adult');
        $this->actingAs($user)->from(route('nutrition-profile.edit'))->put(route('nutrition-profile.update'), ['planning_circumstances_present' => 1, 'height_cm' => 1])->assertSessionHasErrors('height_cm');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('nutrition-profile.edit'))->assertOk();
        $this->assertSame([], $this->selections($response->getContent()));
    }

    public function test_legacy_and_new_request_formats_cannot_be_mixed_and_legacy_updates_clear_stale_selections(): void
    {
        $user = $this->member('needs_review');
        $this->actingAs($user)->putJson(route('nutrition-profile.update'), ['planning_context' => 'general_adult', 'planning_circumstances' => ['pregnant']])
            ->assertUnprocessable()->assertJsonValidationErrors('planning_circumstances');
        $this->assertSame('needs_review', $user->nutritionProfile()->first()->planning_context);
        $this->put(route('nutrition-profile.update'), ['planning_circumstances' => ['medical_dietary_needs']])->assertSessionHasNoErrors();
        $this->put(route('nutrition-profile.update'), ['planning_context' => 'unsure'])->assertSessionHasNoErrors();
        $this->assertNull($user->nutritionProfile()->first()->planning_circumstances);
        $this->assertSame('unsure', $user->nutritionProfile()->first()->planning_context);
    }

    public static function restrictedChoices(): array
    {
        return ['pregnant' => [['pregnant']], 'breastfeeding' => [['breastfeeding']], 'medical' => [['medical_dietary_needs']], 'unsure' => [['not_sure']], 'multiple' => [['pregnant', 'medical_dietary_needs']]];
    }

    #[DataProvider('restrictedChoices')]
    public function test_restricted_selections_block_direct_generation_and_preserve_saved_content(array $selections): void
    {
        Http::preventStrayRequests();
        Queue::fake([GenerateWeeklyMealDraft::class]);
        $user = $this->member('general_adult');
        $snapshot = ['name' => 'Existing meal', 'description' => 'Saved', 'instructions' => 'Prepare.', 'ingredients' => [], 'totals' => ['energy_kcal' => 500]];
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch', 'content_hash' => hash('sha256', 'circumstances'), 'snapshot' => $snapshot]);
        $plan = MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id, 'plan_date' => '2026-11-09', 'slot' => 'lunch', 'snapshot' => $snapshot]);
        $savedBefore = $saved->fresh()->getAttributes();
        $planBefore = $plan->fresh()->getAttributes();
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_circumstances' => $selections])->assertSessionHasNoErrors();
        $this->get(route('meal-ideas.index'))->assertOk()->assertSee('Automatic calorie targets and meal generation');
        $this->get(route('weekly-meals.create'))->assertOk()->assertDontSee('id="weekly-start-form"', false);
        $this->postJson(route('meal-ideas.store'), ['meal_type' => 'lunch'])->assertUnprocessable()->assertJsonValidationErrors('generation');
        $this->postJson(route('weekly-meals.store'), ['start_date' => '2026-11-09'])->assertUnprocessable()->assertJsonValidationErrors('generation');
        $this->assertSame($savedBefore, $saved->fresh()->getAttributes());
        $this->assertSame($planBefore, $plan->fresh()->getAttributes());
        $this->assertDatabaseCount('weekly_meal_drafts', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_none_apply_keeps_existing_calculations_and_other_eligibility_safeguards(): void
    {
        $user = $this->member('general_adult');
        $before = app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first());
        $this->actingAs($user)->put(route('nutrition-profile.update'), ['planning_circumstances' => ['none']])->assertSessionHasNoErrors();
        $this->assertSame($before, app(NutritionTargetCalculator::class)->calculate($user->nutritionProfile()->first()));

        $user->nutritionProfile()->first()->update(['weight_kg' => 100]);
        $this->assertSame('review', app(Onboarding::class)->state($user)['targets']['status']);
        $user->nutritionProfile()->first()->update(['weight_kg' => 80, 'planning_circumstances' => ['medical_dietary_needs'], 'planning_context' => 'general_adult']);
        $this->assertSame('review', app(Onboarding::class)->state($user)['targets']['status']);
    }
}
