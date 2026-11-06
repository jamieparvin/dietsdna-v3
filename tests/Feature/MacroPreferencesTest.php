<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Nutrition\NutritionCalculatorSettings;
use App\Services\Nutrition\NutritionTargetCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MacroPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Run with phpunit-macros.xml. Real databases must not be refreshed.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        DB::table('nutrition_calculator_settings')->updateOrInsert(['id' => 1], [
            'parameters' => json_encode(NutritionCalculatorSettings::defaults(), JSON_THROW_ON_ERROR),
            'revision' => 5, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->owner = $this->userWithProfile();
    }

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        $user->nutritionProfile()->create([
            'date_of_birth' => '1996-10-24', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate',
            'goal' => 'maintain_weight', 'planning_context' => 'general_adult',
            'resistance_training' => false, 'meals_per_day' => 5, 'snacks_per_day' => 1,
        ]);

        return $user;
    }

    private function custom(): array
    {
        return ['macro_mode' => 'custom', 'profile_goal' => 'maintain_weight',
            'macro_protein_percent' => '25', 'macro_carbohydrate_percent' => '45', 'macro_fat_percent' => '30'];
    }

    public function test_guests_cannot_save_macro_preferences(): void
    {
        $this->put(route('nutrition-profile.macros.update'), $this->custom())->assertRedirect(route('login'));
    }

    public function test_custom_split_saves_only_to_the_authenticated_user(): void
    {
        $other = $this->userWithProfile();
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), $this->custom() + ['user_id' => $other->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $p = $this->owner->nutritionProfile()->first();
        $this->assertSame('custom', $p->macro_mode);
        $this->assertSame('maintain_weight', $p->macro_goal);
        $this->assertSame('25.00', $p->macro_protein_percent);
        $this->assertSame('goal', $other->nutritionProfile()->first()->macro_mode);
        $this->assertSame(5, $p->meals_per_day);
        $this->assertSame(1, $p->snacks_per_day);
    }

    public function test_invalid_totals_and_ranges_do_not_change_the_profile(): void
    {
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), array_replace($this->custom(), ['macro_protein_percent' => 26]))
            ->assertSessionHasErrors('macro_protein_percent');
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), array_replace($this->custom(), ['macro_protein_percent' => 50]))
            ->assertSessionHasErrors('macro_protein_percent');
        $this->assertSame('goal', $this->owner->nutritionProfile()->first()->macro_mode);
    }

    public function test_reset_clears_custom_values_and_follows_goal_defaults(): void
    {
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), $this->custom())->assertSessionHasNoErrors();
        $this->put(route('nutrition-profile.macros.update'), ['macro_mode' => 'goal', 'profile_goal' => 'maintain_weight'])
            ->assertSessionHasNoErrors();
        $p = $this->owner->nutritionProfile()->first();
        $this->assertSame('goal', $p->macro_mode);
        $this->assertNull($p->macro_goal);
        $this->assertNull($p->macro_protein_percent);
        $this->assertSame('goal', app(NutritionTargetCalculator::class)->calculate($p)['macro_source']);
    }

    public function test_a_stale_goal_cannot_be_used_to_save_a_custom_split(): void
    {
        $this->owner->nutritionProfile()->update(['goal' => 'build_muscle']);
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), $this->custom())
            ->assertSessionHasErrors('macro_mode');
        $this->assertSame('goal', $this->owner->nutritionProfile()->first()->macro_mode);
    }

    public function test_goal_changes_require_review_without_automatically_erasing_custom_choices(): void
    {
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), $this->custom())->assertSessionHasNoErrors();
        $this->owner->nutritionProfile()->update(['goal' => 'build_muscle']);
        $p = $this->owner->nutritionProfile()->first();
        $this->assertSame('review', app(NutritionTargetCalculator::class)->calculate($p)['status']);
        $this->assertSame('25.00', $p->macro_protein_percent);
        $this->put(route('nutrition-profile.macros.update'), array_replace($this->custom(), ['profile_goal' => 'build_muscle']))
            ->assertSessionHasNoErrors();
        $this->assertSame('ready', app(NutritionTargetCalculator::class)->calculate($p->fresh())['status']);
    }

    public function test_profile_view_renders_explanation_and_the_separate_macro_form(): void
    {
        $this->owner->forceFill(['food_preferences_reviewed_at' => now()])->save();
        $this->actingAs($this->owner)->get(route('nutrition-profile.edit'))
            ->assertOk()->assertSee('Your macro targets')->assertSee('How percentages become grams')
            ->assertSee('macro-preferences-form', false)->assertSee('Optional snacks');
    }

    public function test_existing_admin_form_renders_the_new_goal_percentage_fields(): void
    {
        $this->owner->forceFill(['is_admin' => true])->save();
        $this->actingAs($this->owner)->get(route('admin.calculator-settings.edit'))
            ->assertOk()->assertSee('macro_build_muscle_protein_percent', false)
            ->assertSee('macro_lose_weight_carbohydrate_percent', false);
    }

    public function test_non_admin_users_cannot_change_global_presets(): void
    {
        $data = NutritionCalculatorSettings::defaults() + ['action' => 'save', 'revision' => 5, 'reason' => 'Test'];
        $this->actingAs($this->owner)->put(route('admin.calculator-settings.update'), $data)->assertForbidden();
    }

    public function test_admin_presets_require_one_hundred_percent_and_preserve_revision_on_failure(): void
    {
        $this->owner->forceFill(['is_admin' => true])->save();
        $data = NutritionCalculatorSettings::defaults() + ['action' => 'save', 'revision' => 5, 'reason' => 'Test presets'];
        $data['macro_build_muscle_protein_percent'] = 35;
        $this->actingAs($this->owner)->put(route('admin.calculator-settings.update'), $data)
            ->assertSessionHasErrors('macro_build_muscle_protein_percent');
        $this->assertSame(5, (int) DB::table('nutrition_calculator_settings')->where('id', 1)->value('revision'));
    }

    public function test_admin_valid_changes_are_audited_and_do_not_replace_user_custom_splits(): void
    {
        $this->actingAs($this->owner)->put(route('nutrition-profile.macros.update'), $this->custom())->assertSessionHasNoErrors();
        $this->owner->forceFill(['is_admin' => true])->save();
        $data = NutritionCalculatorSettings::defaults() + ['action' => 'save', 'revision' => 5, 'reason' => 'Revise default split'];
        $data['macro_maintain_weight_protein_percent'] = 20;
        $data['macro_maintain_weight_carbohydrate_percent'] = 50;
        $this->put(route('admin.calculator-settings.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame(6, (int) DB::table('nutrition_calculator_settings')->where('id', 1)->value('revision'));
        $this->assertDatabaseHas('nutrition_calculator_setting_changes', ['revision' => 6, 'reason' => 'Revise default split']);
        $r = app(NutritionTargetCalculator::class)->calculate($this->owner->nutritionProfile()->first());
        $this->assertSame('custom', $r['macro_source']);
        $this->assertSame(25.0, $r['macro_percentages']['protein']);
    }

    public function test_stale_admin_revision_is_rejected(): void
    {
        $this->owner->forceFill(['is_admin' => true])->save();
        $data = NutritionCalculatorSettings::defaults() + ['action' => 'save', 'revision' => 4, 'reason' => 'Stale form'];
        $this->actingAs($this->owner)->put(route('admin.calculator-settings.update'), $data)->assertSessionHasErrors('revision');
        $this->assertSame(5, (int) DB::table('nutrition_calculator_settings')->where('id', 1)->value('revision'));
    }

    public function test_existing_global_calorie_and_activity_values_survive_new_preset_defaults(): void
    {
        DB::table('nutrition_calculator_settings')->where('id', 1)->update([
            'parameters' => json_encode(['gain_surplus' => 350, 'activity_moderate' => 1.6, 'protein_training' => 1.8]),
        ]);
        $values = app(NutritionCalculatorSettings::class)->current()['values'];
        $this->assertEquals(350, $values['gain_surplus']);
        $this->assertEquals(1.6, $values['activity_moderate']);
        $this->assertEquals(30, $values['macro_build_muscle_protein_percent']);
    }
}
