<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\MealPlanSlots;
use App\Services\Meals\PlannedMealSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeneratedMealSwapTest extends TestCase
{
    use RefreshDatabase;

    private string $generationMode = 'different';

    private function fixture(string $slot = 'breakfast'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 12:00:00', 'Europe/London'));
        $user = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $user->nutritionProfile()->create(['date_of_birth' => '1990-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight', 'resistance_training' => false,
            'meals_per_day' => $slot === 'meal_2' ? 4 : 3, 'snacks_per_day' => str_starts_with($slot, 'snack_') ? 2 : 0]);
        $foods = [];
        foreach (['Protein' => [200, 50, 0, 0], 'Carb A' => [400, 0, 100, 0], 'Carb B' => [400, 0, 100, 0], 'Fat A' => [900, 0, 0, 100], 'Fat B' => [900, 0, 0, 100], 'Excluded food' => [100, 10, 10, 2]] as $name => [$kcal, $protein, $carbs, $fat]) {
            $foods[$name] = Ingredient::create(['food_code' => $name, 'name' => $name, 'category' => 'Test', 'sub_category' => 'Test', 'preparation_state' => 'raw', 'is_active' => true,
                'energy_kcal' => $kcal, 'protein_g' => $protein, 'carbohydrate_g' => $carbs, 'fat_g' => $fat, 'fibre_g' => 0, 'sugars_g' => 0, 'saturates_g' => 0, 'salt_g' => 0]);
        }
        DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $foods['Excluded food']->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $profile = $user->nutritionProfile()->first();
        $slots = MealPlanSlots::forCount($profile->meals_per_day, $profile->snacks_per_day);
        $templates = [];
        foreach ($slots as $key => $label) {
            $type = MealPlanSlots::typeForSlot($key);
            if (! isset($templates[$type])) {
                $templates[$type] = $this->snapshot($user, $foods, $type);
            }
        }
        foreach (range(0, 6) as $day) {
            foreach ($slots as $key => $label) {
                MealPlanEntry::create(['user_id' => $user->id, 'plan_date' => CarbonImmutable::parse('2026-11-09')->addDays($day)->toDateString(), 'slot' => $key, 'snapshot' => $templates[MealPlanSlots::typeForSlot($key)]]);
            }
        }
        $entry = MealPlanEntry::where('user_id', $user->id)->whereDate('plan_date', '2026-11-10')->where('slot', $slot)->firstOrFail();

        return [$user, $foods, $entry];
    }

    private function rows(User $user, array $foods, string $type): array
    {
        $target = app(MealIdeaGenerator::class)->context($user, $type)['meal_targets'];

        return [
            ['ingredient_id' => $foods['Protein']->id, 'quantity_g' => round($target['protein_g'] * 2, 2)],
            ['ingredient_id' => $foods['Carb A']->id, 'quantity_g' => round($target['carbohydrate_g'] / 2, 2)],
            ['ingredient_id' => $foods['Carb B']->id, 'quantity_g' => round($target['carbohydrate_g'] / 2, 2)],
            ['ingredient_id' => $foods['Fat A']->id, 'quantity_g' => round($target['fat_g'] / 2, 2)],
            ['ingredient_id' => $foods['Fat B']->id, 'quantity_g' => round($target['fat_g'] / 2, 2)],
        ];
    }

    private function snapshot(User $user, array $foods, string $type): array
    {
        $catalog = collect($foods)->keyBy('id');
        $totals = array_fill_keys(['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g', 'fibre_g', 'sugars_g', 'saturates_g', 'salt_g'], 0.0);
        $rows = $this->rows($user, $foods, $type);
        foreach ($rows as &$row) {
            $food = $catalog[$row['ingredient_id']];
            $row += ['name' => $food->name, 'preparation_state' => $food->preparation_state, 'food_key' => $food->snapshotFoodKey()];
            foreach ($totals as $key => $value) {
                $totals[$key] += (float) $food->{$key} * $row['quantity_g'] / 100;
            }
        }
        unset($row);
        $totals['estimated_available_carbohydrate_g'] = $totals['carbohydrate_g'];

        return ['name' => 'Original '.$type, 'description' => 'Already planned meal', 'instructions' => 'Prepare the original '.$type.' method.', 'ingredients' => $rows, 'totals' => $totals];
    }

    private function fakeIdeas(User $user, array $foods, string $type, string $mode = 'different'): void
    {
        $this->generationMode = $mode;
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.recipe_model' => 'test-model']);
        Http::fake(['api.openai.com/v1/responses' => function ($request) use ($user, $foods, $type) {
            $input = json_decode($request['input'], true);
            $brief = json_decode(substr($input['brief'], strpos($input['brief'], "\n{")), true);
            $this->assertSame($type, $brief['meal_type']);
            $this->assertNotContains($foods['Excluded food']->id, array_column($input['ingredient_catalog'], 'id'));
            $recipes = [];
            foreach (range(0, 2) as $index) {
                $rows = $this->rows($user, $foods, $type);
                if ($this->generationMode === 'copies') {
                    foreach ($rows as &$row) {
                        $row['quantity_g'] = round($row['quantity_g'] * 1.5, 2);
                    }
                    unset($row);
                } elseif ($this->generationMode === 'excluded') {
                    $rows[0]['ingredient_id'] = $foods['Excluded food']->id;
                }
                $recipes[] = ['name' => 'New '.$type.' '.$index, 'description' => 'Generated alternative', 'servings' => 1,
                    'instructions' => $this->generationMode === 'copies' ? 'Prepare the original '.$type.' method.' : ['Layer the foods for this recipe.', 'Roast the foods for this recipe.', 'Combine the foods for this recipe.'][$index], 'ingredients' => $rows];
            }

            return Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => $recipes])]]]]]);
        }]);
    }

    private function token(MealPlanEntry $entry): string
    {
        return app(PlannedMealSnapshot::class)->entryToken($entry->fresh());
    }

    private function generateAndReview(User $user, MealPlanEntry $entry): array
    {
        $this->actingAs($user)->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertSessionHasNoErrors()->assertRedirect(route('meal-plan.swap', $entry));
        $response = $this->get(route('meal-plan.swap', $entry))->assertOk()->assertSee('Use this meal instead');

        return $response->viewData('batch');
    }

    private function confirmData(MealPlanEntry $entry, array $batch): array
    {
        return ['entry_token' => $this->token($entry), 'batch_token' => $batch['batch_token'], 'idea_index' => 0];
    }

    public function test_first_week_without_saved_meals_can_review_and_confirm_one_replacement_without_saving_it(): void
    {
        [$user, $foods, $entry] = $this->fixture();
        $this->fakeIdeas($user, $foods, 'breakfast');
        $before = MealPlanEntry::orderBy('id')->get()->mapWithKeys(fn ($planned) => [$planned->id => $planned->getAttributes()])->all();
        $this->actingAs($user)->get(route('meal-plan.swap', $entry))->assertOk()->assertSee('Generate breakfast ideas')->assertSee('No other eligible saved meals');
        Http::assertNothingSent();
        $batch = $this->generateAndReview($user, $entry);
        $this->assertSame($before, MealPlanEntry::orderBy('id')->get()->mapWithKeys(fn ($planned) => [$planned->id => $planned->getAttributes()])->all());
        $this->assertDatabaseCount('saved_meals', 0);
        $payload = $this->confirmData($entry, $batch);
        $payload['snapshot'] = ['name' => 'Untrusted client recipe'];
        $this->put(route('meal-plan.swap.update', $entry), $payload)->assertSessionHasNoErrors()->assertRedirect(route('meal-plan.index', ['date' => '2026-11-10']));
        $this->get(route('meal-plan.index', ['date' => '2026-11-10']))->assertOk()->assertSee('New breakfast 0');
        $entry->refresh();
        $this->assertSame('New breakfast 0', $entry->snapshot['name']);
        $this->assertSame('2026-11-10', $entry->plan_date->toDateString());
        $this->assertSame('breakfast', $entry->slot);
        $this->assertNull($entry->saved_meal_id);
        foreach (MealPlanEntry::where('id', '!=', $entry->id)->get() as $other) {
            $this->assertSame($before[$other->id], $other->getAttributes());
        }
        $this->assertDatabaseCount('meal_plan_entries', 21);
        $this->assertDatabaseCount('saved_meals', 0);
        $this->put(route('meal-plan.swap.update', $entry), $payload)->assertSessionHasErrors('meal');
        Http::assertSentCount(1);
    }

    public static function slots(): array
    {
        return ['breakfast' => ['breakfast', 'breakfast'], 'lunch' => ['lunch', 'lunch'], 'dinner' => ['dinner', 'dinner'], 'second full meal' => ['meal_2', 'lunch'], 'second snack' => ['snack_2', 'snack']];
    }

    #[DataProvider('slots')]
    public function test_generated_suggestions_keep_the_selected_date_slot_and_mapped_meal_type(string $slot, string $type): void
    {
        [$user, $foods, $entry] = $this->fixture($slot);
        $this->fakeIdeas($user, $foods, $type);
        $batch = $this->generateAndReview($user, $entry);
        $this->assertSame($type, $batch['meal_type']);
        $this->put(route('meal-plan.swap.update', $entry), $this->confirmData($entry, $batch))->assertSessionHasNoErrors();
        $this->assertSame($slot, $entry->fresh()->slot);
        $this->assertSame('2026-11-10', $entry->fresh()->plan_date->toDateString());
        Http::assertSentCount(1);
    }

    public function test_resized_saved_copy_is_hidden_and_rejected_on_direct_post_while_genuine_alternative_remains(): void
    {
        Http::preventStrayRequests();
        [$user, $foods, $entry] = $this->fixture();
        $copy = $entry->snapshot;
        $copy['name'] = 'A renamed and resized copy';
        foreach ($copy['ingredients'] as &$row) {
            $row['quantity_g'] *= 2;
        }
        unset($row);
        $helper = app(PlannedMealSnapshot::class);
        $savedCopy = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'breakfast', 'content_hash' => $helper->hash($copy), 'snapshot' => $copy]);
        $original = $entry->snapshot;
        $this->actingAs($user)->get(route('meal-plan.swap', $entry))->assertOk()->assertDontSee('A renamed and resized copy')->assertSee('No other eligible saved meals');
        $this->put(route('meal-plan.swap.update', $entry), ['entry_token' => $this->token($entry), 'saved_meal_id' => $savedCopy->id])->assertSessionHasErrors('meal');
        $this->assertSame($original, $entry->fresh()->snapshot);
        $copy['name'] = 'A different saved breakfast';
        $copy['instructions'] = 'Bake the foods using this different preparation.';
        $alternative = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'breakfast', 'content_hash' => $helper->hash($copy), 'snapshot' => $copy]);
        $this->get(route('meal-plan.swap', $entry))->assertOk()->assertSee('A different saved breakfast')->assertDontSee('A renamed and resized copy');
        $this->put(route('meal-plan.swap.update', $entry), ['entry_token' => $this->token($entry), 'saved_meal_id' => $alternative->id])->assertSessionHasNoErrors();
        $this->assertSame($alternative->fresh()->snapshot, $entry->fresh()->snapshot);
        $this->assertSame($alternative->id, $entry->fresh()->saved_meal_id);
        Http::assertNothingSent();
    }

    public function test_only_current_recipe_copies_produce_an_explanation_and_allow_a_bounded_retry(): void
    {
        [$user, $foods, $entry] = $this->fixture();
        $this->fakeIdeas($user, $foods, 'breakfast', 'copies');
        $before = $entry->getAttributes();
        $this->actingAs($user)->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertRedirect(route('meal-plan.swap', $entry))->assertSessionHasErrors('generation');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('meal-plan.swap', $entry))->assertOk()->assertSee('No suitable alternatives were found for this slot')->assertSee('Generate breakfast ideas');
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertSentCount(2);
        $this->fakeIdeas($user, $foods, 'breakfast');
        $batch = $this->generateAndReview($user, $entry);
        $this->assertCount(3, $batch['ideas']);
        Http::assertSentCount(1);
    }

    public static function staleChanges(): array
    {
        return ['profile changed' => ['profile'], 'preferences changed' => ['preferences'], 'expired' => ['expired'], 'entry changed' => ['entry']];
    }

    #[DataProvider('staleChanges')]
    public function test_stale_generated_ideas_do_not_change_the_selected_slot(string $change): void
    {
        [$user, $foods, $entry] = $this->fixture();
        $this->fakeIdeas($user, $foods, 'breakfast');
        $batch = $this->generateAndReview($user, $entry);
        $payload = $this->confirmData($entry, $batch);
        if ($change === 'profile') {
            $user->nutritionProfile()->update(['weight_kg' => 81]);
        } elseif ($change === 'preferences') {
            DB::table('user_food_preferences')->insert(['user_id' => $user->id, 'food_key' => $foods['Carb B']->preferenceFoodKey(), 'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($change === 'expired') {
            $this->travelTo(now()->addMinutes(31));
        } else {
            $snapshot = $entry->snapshot;
            $snapshot['instructions'] = 'Changed in another tab';
            $entry->update(['snapshot' => $snapshot]);
        }
        $before = $entry->fresh()->getAttributes();
        $this->put(route('meal-plan.swap.update', $entry), $payload)->assertSessionHasErrors('meal');
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertSentCount(1);
    }

    public function test_batches_are_isolated_by_slot_and_regeneration_invalidates_an_older_tab(): void
    {
        [$user, $foods, $entry] = $this->fixture();
        $this->fakeIdeas($user, $foods, 'breakfast');
        $batch = $this->generateAndReview($user, $entry);
        $other = MealPlanEntry::where('user_id', $user->id)->where('id', '!=', $entry->id)->firstOrFail();
        $this->put(route('meal-plan.swap.update', $other), $this->confirmData($other, $batch))->assertSessionHasErrors('meal');
        $fresh = $this->generateAndReview($user, $entry);
        $this->assertNotSame($batch['batch_token'], $fresh['batch_token']);
        $this->put(route('meal-plan.swap.update', $entry), $this->confirmData($entry, $batch))->assertSessionHasErrors('meal');
        $this->assertSame('Original breakfast', $entry->fresh()->snapshot['name']);
        Http::assertSentCount(2);
    }

    public function test_excluded_ingredients_are_not_accepted_and_schema_failures_do_not_trigger_paid_retries(): void
    {
        [$user, $foods, $entry] = $this->fixture();
        $this->fakeIdeas($user, $foods, 'breakfast', 'excluded');
        $this->actingAs($user)->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertSessionHasErrors('generation')->assertRedirect(route('meal-plan.swap', $entry));
        $this->assertSame('Original breakfast', $entry->fresh()->snapshot['name']);
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertSentCount(1);
    }

    public function test_existing_generation_lock_prevents_another_paid_request(): void
    {
        Http::preventStrayRequests();
        [$user, $foods, $entry] = $this->fixture();
        $lock = Cache::lock('meal-ideas:user:'.$user->id, 240);
        $this->assertTrue($lock->get());
        $this->actingAs($user)->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertSessionHasErrors('generation');
        $lock->release();
        Http::assertNothingSent();
    }

    public function test_other_accounts_cannot_generate_or_confirm_replacements_for_this_entry(): void
    {
        Http::preventStrayRequests();
        [$user, $foods, $entry] = $this->fixture();
        $other = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $other->nutritionProfile()->create($user->nutritionProfile()->first()->only(['date_of_birth', 'height_cm', 'weight_kg', 'calculation_sex', 'activity_level', 'goal', 'resistance_training', 'meals_per_day', 'snacks_per_day']));
        $this->actingAs($other)->get(route('meal-plan.swap', $entry))->assertNotFound();
        $this->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertNotFound();
        $this->put(route('meal-plan.swap.update', $entry), ['entry_token' => $this->token($entry), 'idea_index' => 0, 'batch_token' => str_repeat('a', 64)])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_confirmation_requires_one_source_and_a_current_server_issued_batch(): void
    {
        Http::preventStrayRequests();
        [$user, $foods, $entry] = $this->fixture();
        $this->actingAs($user)->put(route('meal-plan.swap.update', $entry), ['entry_token' => $this->token($entry), 'idea_index' => 0])->assertSessionHasErrors('batch_token');
        $this->put(route('meal-plan.swap.update', $entry), ['entry_token' => $this->token($entry), 'saved_meal_id' => 1, 'idea_index' => 0, 'batch_token' => str_repeat('a', 64)])->assertSessionHasErrors('saved_meal_id');
        $this->put(route('meal-plan.swap.update', $entry), ['entry_token' => $this->token($entry), 'idea_index' => 0, 'batch_token' => str_repeat('a', 64)])->assertSessionHasErrors('meal');
        $this->assertSame('Original breakfast', $entry->fresh()->snapshot['name']);
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertNothingSent();
    }

    public function test_generation_action_is_rate_limited_without_changing_the_plan(): void
    {
        [$user, $foods, $entry] = $this->fixture();
        $this->fakeIdeas($user, $foods, 'breakfast');
        $this->actingAs($user);
        foreach (range(1, 3) as $attempt) {
            $this->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertSessionHasNoErrors()->assertRedirect(route('meal-plan.swap', $entry));
        }
        $this->post(route('meal-plan.swap.generate', $entry), ['entry_token' => $this->token($entry)])->assertTooManyRequests();
        $this->assertSame('Original breakfast', $entry->fresh()->snapshot['name']);
        $this->assertDatabaseCount('saved_meals', 0);
        Http::assertSentCount(3);
    }
}
