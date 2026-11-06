<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSetupComplete;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\ShoppingListCheck;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Nutrition\FoodPreferenceGroups;
use App\Services\Shopping\ShoppingListBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        // This suite exercises meal behavior with minimal profiles; onboarding has its own route coverage.
        $this->withoutMiddleware(EnsureSetupComplete::class);
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-26 12:00:00', 'Europe/London'));
        $this->owner = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function food(string $name = 'Rice', string $preparation = 'dry', array $extra = []): Ingredient
    {
        return Ingredient::create(array_replace(['food_code' => 'SHOP-'.Str::uuid(), 'name' => $name,
            'category' => 'Grains', 'sub_category' => 'Test', 'preparation_state' => $preparation,
            'is_active' => true], $extra));
    }

    private function row(Ingredient $food, mixed $quantity = 100, array $extra = []): array
    {
        return array_replace(['ingredient_id' => $food->id, 'name' => $food->name,
            'preparation_state' => $food->preparation_state, 'quantity_g' => $quantity], $extra);
    }

    private function plan(array $rows, string $date = '2026-10-26', string $slot = 'lunch', ?User $user = null): MealPlanEntry
    {
        $user ??= $this->owner;
        $snapshot = ['name' => 'Meal '.$slot, 'description' => 'Historical meal', 'instructions' => 'Serve the food.',
            'ingredients' => $rows, 'totals' => []];
        $saved = SavedMeal::create(['user_id' => $user->id, 'meal_type' => 'lunch',
            'content_hash' => hash('sha256', $date.$slot.json_encode($snapshot)), 'snapshot' => $snapshot]);

        return MealPlanEntry::create(['user_id' => $user->id, 'saved_meal_id' => $saved->id,
            'plan_date' => $date, 'slot' => $slot, 'snapshot' => $snapshot]);
    }

    private function build(): array
    {
        return app(ShoppingListBuilder::class)->build($this->owner, CarbonImmutable::parse('2026-10-26', 'Europe/London'));
    }

    private function check(array $item, array $changes = [])
    {
        return $this->actingAs($this->owner)->putJson(route('shopping-list.update'), array_replace([
            'week_start' => '2026-10-26', 'item_key' => $item['item_key'],
            'quantity_g' => $item['quantity_g'], 'checked' => true,
        ], $changes));
    }

    public function test_guests_cannot_read_or_change_checks(): void
    {
        $this->get(route('shopping-list.index'))->assertRedirect(route('login'));
        $this->put(route('shopping-list.update'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('shopping_list_checks', 0);
    }

    public function test_owned_confirmed_snapshots_include_sunday_and_exclude_drafts_saved_options_and_adjacent_weeks(): void
    {
        $food = $this->food();
        $this->plan([$this->row($food, 999)], '2026-10-25');
        $monday = $this->plan([$this->row($food, 100)]);
        $this->plan([$this->row($food, 150)], '2026-11-01');
        $this->plan([$this->row($food, 999)], '2026-11-02');
        $this->plan([$this->row($food, 999)], '2026-10-26', 'lunch', User::factory()->create());
        SavedMeal::create(['user_id' => $this->owner->id, 'meal_type' => 'lunch', 'content_hash' => str_repeat('a', 64),
            'snapshot' => ['ingredients' => [$this->row($food, 999)]]]);
        WeeklyMealDraft::create(['user_id' => $this->owner->id, 'start_date' => '2026-10-26', 'status' => 'ready',
            'source_signature' => str_repeat('a', 64), 'meal_count' => 3, 'slots' => [],
            'proposals' => [['snapshot' => ['ingredients' => [$this->row($food, 999)]]]]]);
        SavedMeal::findOrFail($monday->saved_meal_id)->delete();
        $list = $this->build();
        $this->assertSame(2, $list['meal_count']);
        $this->assertSame('250.00', $list['items'][0]['quantity_g']);
        $before = MealPlanEntry::orderBy('id')->get()->toJson();
        $this->actingAs($this->owner)->get(route('shopping-list.index', ['date' => '2026-11-01']))
            ->assertOk()->assertSee('250 g');
        $this->assertSame($before, MealPlanEntry::orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('shopping_list_checks', 0);
        Http::assertNothingSent();
    }

    public function test_raw_dry_and_explicit_ready_to_eat_forms_use_snapshot_grams_and_combine_only_identical_purchases(): void
    {
        $raw = $this->food('Rice', 'raw');
        $dry = $this->food('Rice', 'dry');
        $ready = $this->food('Yogurt', 'ready_to_eat');
        $this->plan([$this->row($raw, 12.345), $this->row($raw, 12.345),
            $this->row($dry, 30), $this->row($ready, 40), $this->row($raw, 50, ['preparation_state' => 'dry'])]);
        $list = $this->build();
        $this->assertFalse($list['incomplete']);
        $this->assertCount(4, $list['items']);
        $quantities = array_column($list['items'], 'quantity_g');
        sort($quantities);
        $this->assertSame(['24.69', '30.00', '40.00', '50.00'], $quantities);
    }

    public function test_valid_explicit_conversions_merge_with_the_same_purchase_and_do_not_follow_preference_groups(): void
    {
        $dry = $this->food('Rice dry');
        $cooked = $this->food('Rice cooked', 'boiled', ['shopping_ingredient_id' => $dry->id,
            'shopping_multiplier' => 0.4, 'is_active' => false]);
        $unmapped = $this->food('Rice cooked', 'boiled', ['is_active' => false]);
        app(FoodPreferenceGroups::class)->link($unmapped, $cooked);
        $this->plan([$this->row($dry, 50), $this->row($cooked, 250), $this->row($unmapped, 100)]);
        $list = $this->build();
        $this->assertSame('150.00', $list['items'][0]['quantity_g']);
        $this->assertCount(1, $list['items']);
        $this->assertCount(1, $list['needs_review']);
        $this->assertSame(100, $list['needs_review'][0]['quantity_g']);
    }

    public function test_mixed_unresolved_cooked_ingredients_keep_original_labels_quantities_and_meal_links(): void
    {
        $raw = $this->food('Raw purchase', 'raw');
        $cooked = $this->food('Original cooked food', 'roasted', ['is_active' => false]);
        $entry = $this->plan([$this->row($raw, 123), $this->row($cooked, 234)]);
        $before = $entry->snapshot;
        $cooked->update(['name' => 'Renamed food']);
        $response = $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk()
            ->assertSee('Incomplete shopping list.')->assertSee('Needs review')->assertSee('123 g')
            ->assertSee('Original cooked food')->assertSee('roasted')->assertSee('234 g')
            ->assertSee('#planned-meal-'.$entry->id, false);
        $this->assertSame(1, $response->viewData('itemCount'));
        $this->assertSame(0, $response->viewData('checkedCount'));
        $this->assertSame($before, $entry->fresh()->snapshot);
        $this->owner->nutritionProfile()->create(['meals_per_day' => 3]);
        $this->get(route('meal-plan.index'))->assertOk()->assertSee('id="planned-meal-'.$entry->id.'"', false);
        $this->get(route('saved-meals.index'))->assertOk()->assertSee('Original cooked food');
    }

    public function test_explicit_mapping_preserves_a_configured_purchase_form_outside_the_direct_allowlist(): void
    {
        $purchase = $this->food('Tinned beans', 'canned, drained', ['is_active' => false]);
        $source = $this->food('Historical beans', 'boiled', ['is_active' => false,
            'shopping_ingredient_id' => $purchase->id, 'shopping_multiplier' => 1]);
        $this->plan([$this->row($source, 125)]);
        $list = $this->build();
        $this->assertFalse($list['incomplete']);
        $this->assertSame('Tinned beans', $list['items'][0]['name']);
        $this->assertSame('canned, drained', $list['items'][0]['preparation_state']);
        $this->assertSame('125.00', $list['items'][0]['quantity_g']);
    }

    public function test_deleted_and_inactive_records_do_not_erase_purchase_ready_snapshots(): void
    {
        $raw = $this->food('Deleted raw', 'raw');
        $cooked = $this->food('Deleted cooked', 'boiled');
        $inactive = $this->food('Inactive ready food', 'ready-to-eat', ['is_active' => false]);
        $this->plan([$this->row($raw, 10), $this->row($cooked, 20), $this->row($inactive, 30)]);
        $raw->delete();
        $cooked->delete();
        $list = $this->build();
        $this->assertCount(2, $list['items']);
        $this->assertCount(1, $list['needs_review']);
        $this->assertSame('Deleted cooked', $list['needs_review'][0]['name']);
        $this->assertContains('Deleted raw', array_column($list['items'], 'name'));
    }

    public static function invalidQuantities(): array
    {
        return array_map(fn ($quantity) => [$quantity], [null, '', 'unknown', 0, -1, true, [], '1e999', 100001]);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_are_reviewed_without_silently_omitting_them(mixed $quantity): void
    {
        $food = $this->food();
        $this->plan([$this->row($food, 50), $this->row($food, $quantity)]);
        $response = $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk()->assertSee('Needs review');
        $list = $this->build();
        $this->assertSame('50.00', $list['items'][0]['quantity_g']);
        $this->assertCount(1, $list['needs_review']);
        $this->assertSame($quantity, $list['needs_review'][0]['quantity_g']);
        $this->assertSame(1, $response->viewData('itemCount'));
    }

    public function test_malformed_and_missing_ingredient_rows_make_the_list_incomplete(): void
    {
        $food = $this->food();
        $row = $this->row($food);
        unset($row['quantity_g']);
        $noId = $this->row($food);
        unset($noId['ingredient_id']);
        $noPreparation = $this->row($food);
        unset($noPreparation['preparation_state']);
        $this->plan([$row, 'invalid row', $noId, $noPreparation]);
        $this->plan([], '2026-10-27');
        $list = $this->build();
        $this->assertCount(5, $list['needs_review']);
        $this->assertSame([], $list['items']);
        $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk()
            ->assertSee('Incomplete shopping list.')->assertSee('0 purchasable items')->assertDontSee('No meals planned for this week');
    }

    public function test_broken_mapping_preparation_mismatch_and_conversion_chains_need_review(): void
    {
        $target = $this->food('Dry purchase');
        $wrong = $this->food('Wrong self factor', 'raw', ['shopping_ingredient_id' => $target->id, 'shopping_multiplier' => 2]);
        $wrong->update(['shopping_ingredient_id' => $wrong->id]);
        $missing = $this->food('Missing mapping target', 'dry', ['shopping_ingredient_id' => $target->id, 'shopping_multiplier' => 0.5]);
        $target->delete();
        $changed = $this->food('Changed preparation', 'boiled', ['shopping_ingredient_id' => $wrong->id, 'shopping_multiplier' => 0.5]);
        $chain = $this->food('Chain', 'dry', ['shopping_ingredient_id' => $wrong->id, 'shopping_multiplier' => 1]);
        $head = $this->food('Head', 'boiled', ['shopping_ingredient_id' => $chain->id, 'shopping_multiplier' => 0.5]);
        $this->plan([$this->row($wrong), $this->row($missing), $this->row($changed, 100, ['preparation_state' => 'roasted']), $this->row($head)]);
        $this->assertCount(4, $this->build()['needs_review']);
        $this->assertSame([], $this->build()['items']);
    }

    public function test_checks_are_idempotent_user_and_week_scoped_and_get_is_read_only(): void
    {
        $food = $this->food();
        $this->plan([$this->row($food)]);
        $item = $this->build()['items'][0];
        $this->check($item)->assertOk()->assertJsonPath('checked_count', 1);
        $this->check($item)->assertOk();
        $this->assertDatabaseCount('shopping_list_checks', 1);
        $other = User::factory()->create();
        $this->actingAs($other)->putJson(route('shopping-list.update'), ['week_start' => '2026-10-26',
            'item_key' => $item['item_key'], 'quantity_g' => 100, 'checked' => false])->assertUnprocessable();
        $this->check($item, ['week_start' => '2026-11-02'])->assertUnprocessable();
        $before = ShoppingListCheck::all()->toJson();
        $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk()
            ->assertViewHas('checkedCount', 1)->assertSee('Purchased items');
        $this->assertSame($before, ShoppingListCheck::all()->toJson());
        $this->check($item, ['checked' => false])->assertOk()->assertJsonPath('checked_count', 0);
        $this->assertDatabaseCount('shopping_list_checks', 0);
    }

    public function test_increases_show_still_needed_and_reject_stale_check_and_uncheck_submissions(): void
    {
        $food = $this->food();
        $this->plan([$this->row($food, 100)]);
        $old = $this->build()['items'][0];
        $this->check($old)->assertOk();
        $this->plan([$this->row($food, 50)], '2026-10-27');
        $response = $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk();
        $this->assertSame(0, $response->viewData('checkedCount'));
        $this->assertCount(1, $response->viewData('groups')->flatten(1));
        $this->check($old)->assertUnprocessable()->assertJsonValidationErrors('shopping_list');
        $this->check($old, ['checked' => false])->assertUnprocessable();
        $this->assertDatabaseHas('shopping_list_checks', ['quantity_g' => 100]);
        $current = $this->build()['items'][0];
        $this->check($current, ['quantity_g' => 999])->assertUnprocessable();
        $this->check($current)->assertOk()->assertJsonPath('checked_count', 1);
        $this->assertDatabaseCount('shopping_list_checks', 1);
    }

    public function test_same_purchase_checks_remain_independent_between_users_and_weeks(): void
    {
        $food = $this->food();
        $other = User::factory()->create();
        $this->plan([$this->row($food, 100)]);
        $this->plan([$this->row($food, 100)], '2026-11-02');
        $this->plan([$this->row($food, 100)], '2026-10-26', 'lunch', $other);
        $item = $this->build()['items'][0];
        $this->check($item)->assertOk();
        $this->check($item, ['week_start' => '2026-11-02'])->assertOk();
        $this->actingAs($other)->get(route('shopping-list.index'))->assertOk()->assertViewHas('checkedCount', 0);
        $this->putJson(route('shopping-list.update'), ['week_start' => '2026-10-26',
            'item_key' => $item['item_key'], 'quantity_g' => 100, 'checked' => true])->assertOk();
        $this->assertDatabaseCount('shopping_list_checks', 3);
        $this->check($item, ['checked' => false])->assertOk();
        $this->assertDatabaseCount('shopping_list_checks', 2);
        $this->assertDatabaseHas('shopping_list_checks', ['user_id' => $other->id, 'week_start' => '2026-10-26']);
        $this->assertDatabaseHas('shopping_list_checks', ['user_id' => $this->owner->id, 'week_start' => '2026-11-02']);
    }

    public function test_existing_midnight_timestamp_checks_are_recognised_without_get_rewriting_them(): void
    {
        $food = $this->food();
        $this->plan([$this->row($food)]);
        $item = $this->build()['items'][0];
        DB::table('shopping_list_checks')->insert([
            'user_id' => $this->owner->id, 'week_start' => '2026-10-26 00:00:00',
            'item_key' => $item['item_key'], 'quantity_g' => 100,
        ]);
        $before = ShoppingListCheck::all()->toJson();
        $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk()->assertViewHas('checkedCount', 1);
        $this->assertSame($before, ShoppingListCheck::all()->toJson());
        $this->check($item)->assertOk();
        $this->assertDatabaseCount('shopping_list_checks', 1);
    }

    public function test_unresolved_items_cannot_be_checked_and_removed_items_reject_stale_forms(): void
    {
        $food = $this->food();
        $entry = $this->plan([$this->row($food)]);
        $item = $this->build()['items'][0];
        $snapshot = $entry->snapshot;
        $snapshot['ingredients'][0]['preparation_state'] = 'boiled';
        $entry->update(['snapshot' => $snapshot]);
        $this->check($item)->assertUnprocessable();
        $this->assertDatabaseCount('shopping_list_checks', 0);
    }

    public function test_navigation_compact_checklist_and_non_javascript_anchor_fallback(): void
    {
        $food = $this->food();
        $this->plan([$this->row($food)]);
        $item = $this->build()['items'][0];
        $this->actingAs($this->owner)->get(route('shopping-list.index'))->assertOk()
            ->assertSee('Jump to shopping category')->assertSee('id="shopping-week"', false)
            ->assertSee('data-shopping-check', false)->assertSee('button.focus({preventScroll:true})', false)
            ->assertSee('href="'.route('shopping-list.index').'"', false);
        $this->assertDoesNotMatchRegularExpression('/Shopping list\s*<\/span>\s*<span class="soon">/u',
            $this->get(route('shopping-list.index'))->getContent());
        $this->put(route('shopping-list.update'), ['week_start' => '2026-10-26', 'item_key' => $item['item_key'],
            'quantity_g' => 100, 'checked' => true])->assertRedirect(route('shopping-list.index', [
                'date' => '2026-10-26', 'purchased' => 1,
            ]).'#shopping-item-'.$item['item_key']);
        $this->get(route('shopping-list.index', ['purchased' => 1]))->assertOk()->assertViewHas('purchasedOpen', true);
    }
}
