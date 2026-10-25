<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Services\Meals\MealIdeaGenerator;
use App\Services\Meals\PlannedMealSnapshot;
use App\Services\Nutrition\FoodPreferenceGroups;
use Database\Seeders\FoodPreferenceGroupingSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class IngredientPreferenceIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['food_code', 'name', 'category', 'sub_category', 'preparation_state', 'energy_kcal',
        'protein_g', 'carbohydrate_g', 'sugars_g', 'fat_g', 'saturates_g', 'fibre_g', 'salt_g', 'is_active'];

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->owner = User::factory()->create();
        $this->owner->nutritionProfile()->create([
            'date_of_birth' => '1996-01-01', 'height_cm' => 180, 'weight_kg' => 80,
            'calculation_sex' => 'male', 'activity_level' => 'moderate', 'goal' => 'maintain_weight',
            'planning_context' => 'general_adult', 'resistance_training' => false,
            'meals_per_day' => 3, 'snacks_per_day' => 0, 'dietary_preference' => 'no_preference',
        ]);
        foreach (range(1, 3) as $index) {
            $this->food('NEUTRAL-'.$index);
        }
    }

    private function food(string $code, array $overrides = []): Ingredient
    {
        return Ingredient::create(array_replace([
            'food_code' => $code, 'name' => $code, 'category' => 'Vegetables',
            'sub_category' => 'Test', 'preparation_state' => 'raw', 'energy_kcal' => 100,
            'protein_g' => 10, 'carbohydrate_g' => 10, 'sugars_g' => 1, 'fat_g' => 2,
            'saturates_g' => 1, 'fibre_g' => 2, 'salt_g' => 0, 'is_active' => true,
        ], $overrides));
    }

    private function choose(User $user, Ingredient $food, string $choice): void
    {
        $this->actingAs($user)->put(route('food-preferences.update'), [
            'preferences' => [$food->fresh()->preferenceFoodKey() => $choice],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function eligibleIds(User $user): array
    {
        return app(MealIdeaGenerator::class)->context($user)['ingredients']->pluck('id')->all();
    }

    private function updateFood(Ingredient $food, array $changes = []): void
    {
        $this->actingAs($this->admin)->put(route('admin.ingredients.update', $food),
            array_replace($food->only(self::HEADERS), $changes))
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    private function workbook(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Ingredients');
        $sheet->fromArray(self::HEADERS, null, 'A1', true);
        foreach ($rows as $index => $row) {
            $sheet->fromArray(array_map(fn ($field) => $row[$field] ?? null, self::HEADERS), null, 'A'.($index + 2), true);
        }
        $path = tempnam(sys_get_temp_dir(), 'dietsdna-workbook-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('foods.xlsx', file_get_contents($path));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    private function preview(array $rows): array
    {
        return $this->actingAs($this->admin)->post(route('admin.ingredients.import.preview'), [
            'workbook' => $this->workbook($rows),
        ])->assertOk()->assertSessionHasNoErrors()->viewData('preview');
    }

    public function test_renaming_and_recategorising_preserve_exclusions_and_snapshot_identity(): void
    {
        $food = $this->food('RAW');
        $this->choose($this->owner, $food, 'excluded');
        $group = $food->preferenceFoodKey();
        $snapshotKey = MealIdeaGenerator::foodKey($food);
        $this->updateFood($food, ['name' => 'Renamed food']);
        $this->assertNotContains($food->id, $this->eligibleIds($this->owner));
        $this->updateFood($food->fresh(), ['category' => 'Changed category']);
        $this->assertNotContains($food->id, $this->eligibleIds($this->owner));
        $this->assertSame($group, $food->fresh()->preferenceFoodKey());
        $this->assertSame($snapshotKey, MealIdeaGenerator::foodKey($food->fresh()));
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $this->owner->id, 'food_key' => $group, 'preference' => 'excluded']);
    }

    public function test_new_preparation_can_be_explicitly_linked_during_creation(): void
    {
        $raw = $this->food('RAW', ['name' => 'Chicken raw']);
        $this->choose($this->owner, $raw, 'excluded');
        $data = array_replace($raw->only(self::HEADERS), [
            'food_code' => 'COOKED', 'name' => 'Roasted chicken', 'preparation_state' => 'roasted',
            'category' => 'Different category', 'link_ingredient_id' => $raw->id,
        ]);
        $this->actingAs($this->admin)->post(route('admin.ingredients.store'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $cooked = Ingredient::where('food_code', 'COOKED')->firstOrFail();
        $this->assertSame($raw->preferenceFoodKey(), $cooked->preferenceFoodKey());
        $this->updateFood($raw, ['name' => 'New name', 'category' => 'New category']);
        $this->assertNotContains($raw->id, $this->eligibleIds($this->owner));
        $this->assertNotContains($cooked->id, $this->eligibleIds($this->owner));
        $this->actingAs($this->admin)->get(route('admin.ingredients.edit', $cooked))
            ->assertOk()->assertSee('Link to an existing food group')->assertSee('New name');
    }

    public function test_linking_merges_whole_groups_with_exclusions_winning_for_each_user(): void
    {
        $raw = $this->food('RAW');
        $cooked = $this->food('COOKED');
        $otherPreparation = $this->food('STEAMED');
        app(FoodPreferenceGroups::class)->link($otherPreparation, $cooked);
        $other = User::factory()->create();
        $untouched = $this->food('UNRELATED');
        $this->choose($this->owner, $raw, 'preferred');
        $this->choose($this->owner, $cooked, 'excluded');
        $this->choose($other, $raw, 'excluded');
        $this->choose($other, $cooked, 'preferred');
        $this->choose($other, $untouched, 'preferred');
        $this->updateFood($cooked, ['link_ingredient_id' => $raw->id]);
        foreach ([$this->owner, $other] as $user) {
            $this->assertDatabaseHas('user_food_preferences', [
                'user_id' => $user->id, 'food_key' => $raw->preferenceFoodKey(), 'preference' => 'excluded',
            ]);
        }
        $this->assertSame($raw->preferenceFoodKey(), $otherPreparation->fresh()->preferenceFoodKey());
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $other->id,
            'food_key' => $untouched->preferenceFoodKey(), 'preference' => 'preferred']);
        $this->assertDatabaseMissing('user_food_preferences', ['user_id' => $this->owner->id, 'food_key' => $untouched->preferenceFoodKey()]);
        $this->assertNotContains($otherPreparation->id, $this->eligibleIds($this->owner));
    }

    public function test_a_users_choice_does_not_become_another_users_choice(): void
    {
        $source = $this->food('SOURCE');
        $target = $this->food('TARGET');
        $other = User::factory()->create();
        $this->choose($this->owner, $source, 'excluded');
        $this->choose($other, $target, 'preferred');
        $this->updateFood($source, ['link_ingredient_id' => $target->id]);
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $this->owner->id,
            'food_key' => $target->preferenceFoodKey(), 'preference' => 'excluded']);
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $other->id,
            'food_key' => $target->preferenceFoodKey(), 'preference' => 'preferred']);
    }

    public function test_identical_or_similar_names_do_not_create_implicit_links(): void
    {
        $a = $this->food('A', ['name' => 'Chicken']);
        $b = $this->food('B', ['name' => 'Chicken']);
        $c = $this->food('C', ['name' => 'Chicken, cooked']);
        $this->choose($this->owner, $a, 'excluded');
        $this->assertNotSame($a->preferenceFoodKey(), $b->preferenceFoodKey());
        $this->assertNotSame($a->preferenceFoodKey(), $c->preferenceFoodKey());
        $this->assertContains($b->id, $this->eligibleIds($this->owner));
        $this->assertContains($c->id, $this->eligibleIds($this->owner));
    }

    public function test_excel_updates_keep_existing_groups_and_new_rows_can_be_linked_after_import(): void
    {
        $raw = $this->food('RAW');
        $cooked = $this->food('COOKED');
        app(FoodPreferenceGroups::class)->link($cooked, $raw);
        $this->choose($this->owner, $raw, 'excluded');
        $key = $raw->preferenceFoodKey();
        $snapshotKey = $cooked->snapshotFoodKey();
        $preview = $this->preview([
            array_replace($cooked->only(self::HEADERS), ['name' => 'Import rename', 'category' => 'Import category']),
            array_replace($raw->only(self::HEADERS), ['food_code' => 'NEW', 'name' => $raw->name]),
        ]);
        $this->actingAs($this->admin)->post(route('admin.ingredients.import.confirm'), ['token' => $preview['token']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($key, $cooked->fresh()->preferenceFoodKey());
        $this->assertSame($snapshotKey, $cooked->fresh()->snapshotFoodKey());
        $this->assertNotContains($cooked->id, $this->eligibleIds($this->owner));
        $new = Ingredient::where('food_code', 'NEW')->firstOrFail();
        $this->assertNotSame($key, $new->preferenceFoodKey());
        $this->updateFood($new, ['link_ingredient_id' => $raw->id]);
        $this->assertNotContains($new->id, $this->eligibleIds($this->owner));
        $this->assertSame('100.00000', $cooked->fresh()->energy_kcal);
        $this->assertSame('2.00000', $cooked->fresh()->fibre_g);
    }

    public function test_import_rejects_a_group_change_since_preview_without_overwriting_it(): void
    {
        $source = $this->food('SOURCE');
        $target = $this->food('TARGET');
        $preview = $this->preview([array_replace($source->only(self::HEADERS), ['name' => 'Should not save'])]);
        $this->updateFood($source, ['link_ingredient_id' => $target->id]);
        $this->actingAs($this->admin)->post(route('admin.ingredients.import.confirm'), ['token' => $preview['token']])
            ->assertSessionHasErrors('workbook');
        $this->assertSame('SOURCE', $source->fresh()->name);
        $this->assertSame($target->preferenceFoodKey(), $source->fresh()->preferenceFoodKey());
    }

    public function test_unchanged_import_stays_unchanged_and_does_not_replace_choices(): void
    {
        $food = $this->food('SAME');
        $this->choose($this->owner, $food, 'preferred');
        $preview = $this->preview([$food->only(self::HEADERS)]);
        $this->assertSame(['new' => 0, 'update' => 0, 'unchanged' => 1], $preview['counts']);
        $this->actingAs($this->admin)->post(route('admin.ingredients.import.confirm'), ['token' => $preview['token']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_food_preferences', ['user_id' => $this->owner->id,
            'food_key' => $food->preferenceFoodKey(), 'preference' => 'preferred']);
    }

    public function test_saved_and_planned_snapshots_and_their_eligibility_survive_group_linking(): void
    {
        $source = $this->food('SOURCE');
        $target = $this->food('TARGET');
        $snapshot = ['name' => 'Historical meal', 'ingredients' => [[
            'ingredient_id' => $source->id, 'food_key' => MealIdeaGenerator::foodKey($source), 'quantity_g' => 123,
        ]], 'totals' => ['energy_kcal' => 123]];
        $saved = SavedMeal::create(['user_id' => $this->owner->id, 'meal_type' => 'lunch',
            'content_hash' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot]);
        $planned = MealPlanEntry::create(['user_id' => $this->owner->id, 'saved_meal_id' => $saved->id,
            'plan_date' => '2026-10-26', 'slot' => 'lunch', 'snapshot' => $snapshot]);
        $savedBefore = $saved->fresh()->getRawOriginal();
        $plannedBefore = $planned->fresh()->getRawOriginal();
        $this->updateFood($source, ['link_ingredient_id' => $target->id, 'name' => 'Renamed']);
        $this->assertSame($savedBefore, $saved->fresh()->getRawOriginal());
        $this->assertSame($plannedBefore, $planned->fresh()->getRawOriginal());
        $this->assertTrue(app(PlannedMealSnapshot::class)->eligible($snapshot, Ingredient::all()));
    }

    public function test_curated_seeder_merges_conflicts_and_is_repeatable_without_matching_nearby_names(): void
    {
        $raw = $this->food('RAW', ['name' => 'Chicken breast, raw']);
        $cooked = $this->food('COOKED', ['name' => 'Chicken breast, cooked']);
        $unlisted = $this->food('UNLISTED', ['name' => 'Chicken breast, cooked differently']);
        $rawSnapshotKey = $raw->snapshotFoodKey();
        $this->choose($this->owner, $raw, 'preferred');
        $this->choose($this->owner, $cooked, 'excluded');
        $this->seed(FoodPreferenceGroupingSeeder::class);
        $this->seed(FoodPreferenceGroupingSeeder::class);
        $this->assertSame($raw->fresh()->preferenceFoodKey(), $cooked->fresh()->preferenceFoodKey());
        $this->assertSame($rawSnapshotKey, $raw->fresh()->snapshotFoodKey());
        $this->assertNotSame($raw->fresh()->preferenceFoodKey(), $unlisted->fresh()->preferenceFoodKey());
        $this->assertNotContains($raw->id, $this->eligibleIds($this->owner));
        $this->assertNotContains($cooked->id, $this->eligibleIds($this->owner));
        $this->assertContains($unlisted->id, $this->eligibleIds($this->owner));
    }

    public function test_migration_freezes_legacy_groups_without_rewriting_any_users_choices(): void
    {
        $raw = $this->food('RAW');
        $cooked = $this->food('COOKED');
        foreach ([$raw, $cooked] as $food) {
            DB::table('ingredients')->where('id', $food->id)->update(['preference_food' => 'Legacy food']);
        }
        $legacyKey = hash('sha256', json_encode(['vegetables', 'legacy food'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        DB::table('user_food_preferences')->insert(['user_id' => $this->owner->id, 'food_key' => $legacyKey,
            'preference' => 'excluded', 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::table('user_food_preferences')->orderBy('id')->get()->toJson();
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['preference_group_key']);
            $table->dropColumn(['preference_key', 'preference_group_key']);
        });
        $migration = require database_path('migrations/2026_10_25_030000_persist_ingredient_preference_identities.php');
        $migration->up();
        $this->assertSame($legacyKey, $raw->fresh()->preferenceFoodKey());
        $this->assertSame($legacyKey, $cooked->fresh()->snapshotFoodKey());
        $this->assertSame($before, DB::table('user_food_preferences')->orderBy('id')->get()->toJson());
        $this->assertNotContains($raw->id, $this->eligibleIds($this->owner));
        $this->assertNotContains($cooked->id, $this->eligibleIds($this->owner));
    }

    public function test_only_admins_can_link_and_invalid_targets_do_not_change_groups(): void
    {
        $food = $this->food('SOURCE');
        $target = $this->food('TARGET');
        $key = $food->preferenceFoodKey();
        $data = array_replace($food->only(self::HEADERS), ['link_ingredient_id' => $target->id]);
        $this->actingAs($this->owner)->put(route('admin.ingredients.update', $food), $data)->assertForbidden();
        $this->actingAs($this->admin)->put(route('admin.ingredients.update', $food),
            array_replace($data, ['link_ingredient_id' => 999999]))->assertSessionHasErrors('link_ingredient_id');
        $this->assertSame($key, $food->fresh()->preferenceFoodKey());
        Http::assertNothingSent();
    }
}
