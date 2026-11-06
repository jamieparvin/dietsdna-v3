<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSetupComplete;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SavedMealDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Run with phpunit-saved-meals.xml and disposable SQLite.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // This suite exercises meal behavior with minimal profiles; onboarding has its own route coverage.
        $this->withoutMiddleware(EnsureSetupComplete::class);
        Http::preventStrayRequests();
    }

    private function meal(User $user, string $type = 'breakfast'): SavedMeal
    {
        return SavedMeal::query()->create([
            'user_id' => $user->id,
            'content_hash' => hash('sha256', uniqid('', true)),
            'meal_type' => $type,
            'snapshot' => [
                'name' => 'Test saved option', 'description' => 'Test meal',
                'instructions' => 'Serve the listed foods.', 'ingredients' => [],
                'totals' => ['energy_kcal' => 500, 'protein_g' => 40,
                    'carbohydrate_g' => 50, 'fat_g' => 15],
            ],
        ]);
    }

    public function test_owner_can_delete_an_unplanned_option(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user);
        $this->actingAs($user)->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseMissing('saved_meals', ['id' => $meal->id]);
    }

    public function test_deletion_keeps_every_dated_snapshot_and_other_saved_options(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user);
        $other = $this->meal($user);
        $entries = [];
        foreach (['2026-11-02', '2026-11-03'] as $date) {
            $entries[] = MealPlanEntry::query()->create([
                'user_id' => $user->id, 'saved_meal_id' => $meal->id,
                'plan_date' => $date, 'slot' => 'breakfast', 'snapshot' => $meal->snapshot,
            ]);
        }
        $this->actingAs($user)->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1])
            ->assertSessionHasNoErrors();
        foreach ($entries as $entry) {
            $fresh = $entry->fresh();
            $this->assertNotNull($fresh);
            $this->assertNull($fresh->saved_meal_id);
            $this->assertSame($entry->snapshot, $fresh->snapshot);
            $this->assertSame($entry->plan_date->format('Y-m-d'), $fresh->plan_date->format('Y-m-d'));
            $this->assertSame('breakfast', $fresh->slot);
        }
        $this->assertDatabaseHas('saved_meals', ['id' => $other->id]);
        $this->assertDatabaseCount('meal_plan_entries', 2);
    }

    public function test_another_users_option_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $meal = $this->meal($owner);
        $this->actingAs(User::factory()->create())->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1])->assertNotFound();
        $this->assertDatabaseHas('saved_meals', ['id' => $meal->id]);
    }

    public function test_guests_cannot_delete(): void
    {
        $meal = $this->meal(User::factory()->create());
        $this->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1])->assertRedirect(route('login'));
        $this->assertDatabaseHas('saved_meals', ['id' => $meal->id]);
    }

    public function test_confirmation_and_valid_return_fields_are_required(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user);
        $this->actingAs($user)->delete(route('saved-meals.destroy', $meal->id), [])->assertSessionHasErrors('delete_confirmed');
        $this->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1, 'date' => 'bad', 'breakfast_page' => 0])->assertSessionHasErrors(['date', 'breakfast_page']);
        $this->assertDatabaseHas('saved_meals', ['id' => $meal->id]);
    }

    public function test_delete_button_is_available_without_profile_or_meal_slots(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user);
        $this->actingAs($user)->get(route('saved-meals.index'))->assertOk()
            ->assertSee('data-open-delete', false)
            ->assertSee(route('saved-meals.destroy', $meal->id), false)
            ->assertSee('Delete saved meal?');
        $this->assertDatabaseHas('saved_meals', ['id' => $meal->id]);
    }

    public function test_last_item_on_page_returns_to_previous_page_and_preserves_context(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 7; $i++) {
            $meal = $this->meal($user);
        }
        $response = $this->actingAs($user)->delete(route('saved-meals.destroy', $meal->id), [
            'delete_confirmed' => 1, 'date' => '2026-11-04', 'breakfast_page' => 2, 'lunch_page' => 3,
        ]);
        $response->assertRedirect(route('saved-meals.index', [
            'date' => '2026-11-04', 'breakfast_page' => 1, 'lunch_page' => 3,
        ]).'#breakfast');
        $this->assertDatabaseCount('saved_meals', 6);
    }

    public function test_repeated_delete_does_not_delete_a_different_option(): void
    {
        $user = User::factory()->create();
        $meal = $this->meal($user);
        $other = $this->meal($user);
        $this->actingAs($user)->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1])->assertRedirect();
        $this->delete(route('saved-meals.destroy', $meal->id), ['delete_confirmed' => 1])->assertNotFound();
        $this->assertDatabaseHas('saved_meals', ['id' => $other->id]);
    }
}
