<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Services\Meals\MealPlanSlots;
use App\Services\Nutrition\NutritionTargetCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private array $guide;
    private array $calculatedProfiles = [];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Run DashboardTest using phpunit-dashboard.xml with disposable in-memory SQLite.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 12:00:00', 'UTC'));
        $this->owner = User::factory()->create(['name' => 'Jamie Dashboard']);
        $this->owner->nutritionProfile()->create(['goal' => 'build_muscle', 'meals_per_day' => 5, 'snacks_per_day' => 1, 'weight_kg' => 80]);
        $this->guide = ['status' => 'ready', 'message' => 'Test guide', 'calories_kcal' => 2000,
            'protein_g' => 150, 'carbohydrate_g' => 200, 'fat_g' => 200/3, 'macro_source' => 'goal'];
        $this->mock(NutritionTargetCalculator::class, function ($mock) {
            $mock->shouldReceive('calculate')->andReturnUsing(function ($profile) {
                $this->calculatedProfiles[] = $profile?->user_id;
                return $profile ? $this->guide : ['status' => 'incomplete', 'message' => 'Save your profile to see an estimate.'];
            });
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function planned(User $user, string $date = '2026-10-25', string $slot = 'breakfast', ?array $nutrition = null, string $name = 'Today owned meal'): MealPlanEntry
    {
        $snapshot = ['name' => $name, 'instructions' => 'Prepare the listed ingredients.',
            'ingredients' => [['name' => 'Oats', 'quantity_g' => 50, 'preparation_state' => 'raw']],
            'totals' => $nutrition ?? ['energy_kcal' => 500, 'protein_g' => 40, 'carbohydrate_g' => 60, 'fat_g' => 12, 'fibre_g' => 10]];
        $saved = SavedMeal::query()->create(['user_id' => $user->id, 'meal_type' => 'breakfast',
            'content_hash' => hash('sha256', uniqid('', true)), 'snapshot' => $snapshot]);
        return MealPlanEntry::query()->create(['user_id' => $user->id, 'saved_meal_id' => $saved->id,
            'plan_date' => $date, 'slot' => $slot, 'snapshot' => $snapshot]);
    }

    public function test_dashboard_is_authenticated_and_uses_the_new_controller(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $route = app('router')->getRoutes()->getByName('dashboard');

        $this->assertSame(
            DashboardController::class,
            $route->getControllerClass()
        );
    }

    public function test_empty_dashboard_shows_current_guides_and_all_full_meal_slots(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $response->viewData('plannedCount'));
        $this->assertSame(6, $response->viewData('patternCount'));
        $this->assertSame(array_keys(MealPlanSlots::forCount(5, 1)), array_column($response->viewData('rows'), 'slot'));
        $this->assertEquals(2000, $response->viewData('metrics')[0]['guide']);
        $this->assertEquals(0, $response->viewData('metrics')[0]['planned']);
        $this->assertSame([$this->owner->id], $this->calculatedProfiles);
        $response->assertSee('Today’s menu')->assertSee('Generate my week')->assertSee('Available carbs')
            ->assertDontSee('Available when food logging is connected.');
    }

    public function test_only_todays_owned_snapshots_are_counted_without_rewriting_them(): void
    {
        $entry = $this->planned($this->owner);
        $this->planned($this->owner, '2026-10-24', 'lunch', null, 'Yesterday only meal');
        $this->planned(User::factory()->create(), '2026-10-25', 'dinner', null, 'Private other meal');
        $before = $entry->snapshot;
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
            ->assertSee('Today owned meal')->assertDontSee('Yesterday only meal')->assertDontSee('Private other meal');
        $this->assertEquals(500, $response->viewData('totals')['energy_kcal']);
        $this->assertEquals(50, $response->viewData('totals')['estimated_available_carbohydrate_g']);
        $this->assertEquals(60, $response->viewData('totals')['carbohydrate_g']);
        $this->assertSame(1, $response->viewData('currentFilled'));
        $this->assertSame($before, $entry->fresh()->snapshot);
    }

    public function test_week_includes_sunday_and_excludes_both_adjacent_weeks(): void
    {
        $this->planned($this->owner, '2026-10-18');
        $this->planned($this->owner, '2026-10-19');
        $this->planned($this->owner, '2026-10-25');
        $this->planned($this->owner, '2026-10-26');
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $days = $response->viewData('weekDays');
        $this->assertSame('2026-10-19', $days[0]['date']->toDateString());
        $this->assertSame('2026-10-25', $days[6]['date']->toDateString());
        $this->assertSame(1, $days[6]['count']);
        $this->assertSame(2, $response->viewData('weekPlannedDays'));
    }

    public function test_local_london_date_is_used_around_midnight_in_summer(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05 23:30:00', 'UTC'));
        $this->planned($this->owner, '2026-07-06', 'breakfast', null, 'London Monday meal');
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('London Monday meal');
        $this->assertSame('2026-07-06', $response->viewData('date'));
        $this->assertSame('2026-07-06', $response->viewData('weekStart')->toDateString());
    }

    public function test_an_unlinked_planned_meal_remains_visible_after_saved_option_deletion(): void
    {
        $entry = $this->planned($this->owner);
        SavedMeal::query()->findOrFail($entry->saved_meal_id)->delete();
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Today owned meal');
        $this->assertNull($entry->fresh()->saved_meal_id);
        $this->assertSame(0, $response->viewData('savedCount'));
        $this->assertEquals(500, $response->viewData('totals')['energy_kcal']);
    }

    public function test_existing_previous_pattern_slots_remain_visible_and_counted(): void
    {
        $this->owner->nutritionProfile()->update(['meals_per_day' => 3, 'snacks_per_day' => 0]);
        $this->planned($this->owner, '2026-10-25', 'snack_2', null, 'Previous snack');
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Previous snack')->assertSee('previous pattern');
        $this->assertSame(3, $response->viewData('patternCount'));
        $this->assertSame(0, $response->viewData('currentFilled'));
        $this->assertSame(1, $response->viewData('plannedCount'));
        $this->assertEquals(500, $response->viewData('totals')['energy_kcal']);
    }

    public function test_missing_profile_and_review_status_do_not_show_fabricated_guides(): void
    {
        $this->owner->nutritionProfile()->delete();
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Set your daily nutrition guide');
        $this->assertNull($response->viewData('metrics')[0]['guide']);
        $this->assertSame(0, $response->viewData('patternCount'));
        $this->owner->nutritionProfile()->create(['meals_per_day' => 3]);
        $this->guide = ['status' => 'review', 'message' => 'Individual review is required.'];
        $this->get(route('dashboard'))->assertOk()->assertSee('Individual review is required.');
    }

    public function test_missing_historical_fibre_is_unknown_instead_of_zero(): void
    {
        $this->planned($this->owner, '2026-10-25', 'breakfast', ['energy_kcal' => 500, 'protein_g' => 40, 'carbohydrate_g' => 60, 'fat_g' => 12]);
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Some planned nutrition is unavailable.');
        $this->assertNull($response->viewData('totals')['estimated_available_carbohydrate_g']);
        $this->assertEquals(60, $response->viewData('totals')['carbohydrate_g']);
    }

    public function test_stored_available_carbohydrate_is_used_when_present(): void
    {
        $this->planned($this->owner, '2026-10-25', 'breakfast', ['energy_kcal' => 500, 'protein_g' => 40,
            'carbohydrate_g' => 60, 'fat_g' => 12, 'estimated_available_carbohydrate_g' => 47]);
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $this->assertEquals(47, $response->viewData('metrics')[2]['planned']);
        $this->assertEquals(200, $response->viewData('metrics')[2]['guide']);
    }

    public function test_current_custom_macro_guide_is_used_and_bars_do_not_overflow(): void
    {
        $this->guide['macro_source'] = 'custom';
        $this->guide['calories_kcal'] = 1800;
        $this->planned($this->owner, '2026-10-25', 'breakfast', ['energy_kcal' => 2000, 'protein_g' => 200,
            'carbohydrate_g' => 200, 'fat_g' => 100, 'fibre_g' => 10]);
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Your custom split')->assertSee('above guide')->assertSee('style="width:100%"', false);
        $this->assertEquals(1800, $response->viewData('metrics')[0]['guide']);
    }

    public function test_saved_count_is_owned_and_get_does_not_write_or_call_ai(): void
    {
        $this->planned($this->owner);
        $this->planned(User::factory()->create());
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $response->viewData('savedCount'));
        $this->assertDatabaseCount('saved_meals', 2);
        $this->assertDatabaseCount('meal_plan_entries', 2);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }
    public function test_snapshot_names_and_methods_are_escaped_in_the_view(): void
    {
        $entry = $this->planned($this->owner, '2026-10-25', 'breakfast', null, '<script>alert("name")</script>');
        $snapshot = $entry->snapshot;
        $snapshot['instructions'] = '<script>alert("method")</script>';
        $entry->update(['snapshot' => $snapshot]);
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
            ->assertSee('<script>alert("name")</script>')
            ->assertDontSee('<script>alert("name")</script>', false)
            ->assertDontSee('<script>alert("method")</script>', false);
    }

}
