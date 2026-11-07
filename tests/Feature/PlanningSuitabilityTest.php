<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Nutrition\NutritionTargetCalculator;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanningSuitabilityTest extends TestCase
{
    use RefreshDatabase;

    private function member(?string $answer = null, ?array $circumstances = null): User
    {
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create([
            'date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => $answer, 'planning_circumstances' => $circumstances, 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0,
        ]);

        return $user;
    }

    public static function historicalAnswers(): array
    {
        return ['never answered' => [null, null], 'No' => ['general_adult', null], 'Yes' => ['specialist_support', null],
            'Unsure' => ['unsure', null], 'legacy review' => ['needs_review', null], 'empty checkbox save' => [null, []],
            'multiple circumstances' => ['specialist_support', ['pregnant', 'medical_dietary_needs']], 'unsure checkbox' => ['unsure', ['not_sure']]];
    }

    #[DataProvider('historicalAnswers')]
    public function test_retired_answers_do_not_determine_targets_or_setup_completion_and_are_preserved(?string $answer, ?array $circumstances): void
    {
        $user = $this->member($answer, $circumstances);
        $profile = $user->nutritionProfile()->first();
        $before = $profile->getAttributes();
        $control = clone $profile;
        $control->planning_context = 'general_adult';
        $control->planning_circumstances = null;
        $calculator = app(NutritionTargetCalculator::class);
        $this->assertSame('ready', $calculator->calculate($profile)['status']);
        $this->assertSame($calculator->calculate($control), $calculator->calculate($profile));
        $this->assertTrue(app(Onboarding::class)->state($user)['complete']);
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertDontSee('Do you need specialist nutrition support?')
            ->assertDontSee('name="planning_context"', false)->assertDontSee('name="planning_circumstances[]"', false)
            ->assertSee('standard meal plans for general adult meal planning')->assertSee('Important: food allergies');
        $this->assertSame($before, $user->nutritionProfile()->first()->getAttributes());
        $this->put(route('nutrition-profile.update'), ['height_cm' => 181, 'planning_context' => 'invalid retired answer', 'planning_circumstances' => ['none', 'pregnant']])->assertSessionHasNoErrors();
        $profile = $user->nutritionProfile()->first();
        $this->assertSame($answer, $profile->planning_context);
        $this->assertSame($circumstances, $profile->planning_circumstances);
        $this->assertSame('181.00', $profile->height_cm);
    }

    public function test_partial_saves_and_validation_errors_do_not_request_or_update_the_retired_answer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('nutrition-profile.edit'))->assertOk()->assertDontSee('specialist nutrition support');
        $this->put(route('nutrition-profile.update'), ['height_cm' => 180])->assertSessionHasNoErrors();
        $this->assertFalse(app(Onboarding::class)->state($user)['profileComplete']);
        $this->assertNull($user->nutritionProfile()->first()->planning_context);
        $this->putJson(route('nutrition-profile.update'), ['calculation_sex' => 'invalid', 'planning_context' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('calculation_sex')->assertJsonMissingValidationErrors('planning_context');
        $this->assertNull($user->nutritionProfile()->first()->planning_context);
    }
}
