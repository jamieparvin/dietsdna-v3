<?php

namespace Tests\Feature;

use App\Models\MealPlanEntry;
use App\Models\SavedMeal;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Nutrition\NutritionTargetCalculator;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistoricalNutritionDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-26 12:00:00', 'Europe/London'));
        $this->owner = User::factory()->create(['food_preferences_reviewed_at' => now()]);
        $this->owner->nutritionProfile()->create(['meals_per_day' => 3, 'snacks_per_day' => 0]);
        $this->mock(NutritionTargetCalculator::class, function ($mock) {
            $mock->shouldReceive('calculate')->andReturn(['status' => 'ready', 'calories_kcal' => 2000,
                'protein_g' => 150, 'carbohydrate_g' => 200, 'fat_g' => 70]);
        });
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function snapshot(array $nutrition = []): array
    {
        return ['name' => 'Historical meal', 'description' => 'Saved food', 'instructions' => 'Prepare the food.',
            'ingredients' => [['name' => 'Food', 'preparation_state' => 'raw', 'quantity_g' => 100]],
            'totals' => $nutrition,
            'portion_balance' => ['daily_targets' => ['calories_kcal' => 2000, 'protein_g' => 150,
                'carbohydrate_g' => 200, 'fat_g' => 70]]];
    }

    private function plan(array $snapshot, string $slot = 'breakfast'): MealPlanEntry
    {
        $saved = SavedMeal::create(['user_id' => $this->owner->id, 'meal_type' => 'breakfast',
            'content_hash' => hash('sha256', $slot.json_encode($snapshot)), 'snapshot' => $snapshot]);

        return MealPlanEntry::create(['user_id' => $this->owner->id, 'saved_meal_id' => $saved->id,
            'plan_date' => '2026-10-26', 'slot' => $slot, 'snapshot' => $snapshot]);
    }

    private function draft(array $overrides = []): WeeklyMealDraft
    {
        return WeeklyMealDraft::create(array_replace(['user_id' => $this->owner->id,
            'start_date' => '2026-10-26', 'status' => 'ready', 'source_signature' => str_repeat('a', 64),
            'meal_count' => 3, 'slots' => ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner'],
            'mode' => 'fill', 'proposals' => [],
        ], $overrides));
    }

    private function metrics(string $html, string $class): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $nodes = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]');
        $result = [];
        foreach ($nodes as $node) {
            $result[] = $document->saveHTML($node);
        }

        return $result;
    }

    private function assertUnknownMetric(string $html, string $trackClass): void
    {
        $this->assertStringContainsString('—', $html);
        $this->assertStringNotContainsString('class="'.$trackClass.'"', $html);
        $this->assertStringNotContainsString('above', strip_tags($html));
        $this->assertStringNotContainsString('below', strip_tags($html));
    }

    public static function incompleteNutrition(): array
    {
        $known = ['protein_g' => 20, 'carbohydrate_g' => 60, 'fat_g' => 0];

        return [
            'missing energy and fibre' => [$known],
            'null energy and fibre' => [$known + ['energy_kcal' => null, 'fibre_g' => null]],
            'invalid strings' => [$known + ['energy_kcal' => 'not known', 'fibre_g' => 'bad']],
            'negative values' => [$known + ['energy_kcal' => -100, 'fibre_g' => -1]],
            'non numeric types' => [$known + ['energy_kcal' => [], 'fibre_g' => false]],
            'non finite numeric strings' => [$known + ['energy_kcal' => '1e999', 'fibre_g' => '1e999']],
            'fibre above total' => [$known + ['energy_kcal' => null, 'fibre_g' => 61]],
        ];
    }

    #[DataProvider('incompleteNutrition')]
    public function test_historical_nutrition_is_consistent_across_pages_and_mixed_meals(array $incomplete): void
    {
        $entry = $this->plan($this->snapshot($incomplete));
        $this->plan($this->snapshot(['energy_kcal' => 500, 'protein_g' => 30,
            'carbohydrate_g' => 50, 'fat_g' => 0, 'fibre_g' => 5]), 'lunch');
        $savedBefore = SavedMeal::orderBy('id')->get()->toJson();
        $entriesBefore = MealPlanEntry::orderBy('id')->get()->toJson();
        $draft = $this->draft();
        $dashboard = $this->get(route('dashboard'))->assertOk()->assertSee('— kcal')->assertSee('g total carbs');
        $daily = $this->get(route('meal-plan.index', ['date' => '2026-10-26']))->assertOk()->assertSee('— kcal')->assertSee('g total carbs');
        $weekly = $this->get(route('weekly-meals.show', $draft))->assertOk()->assertSee('g total carbs');
        $this->get(route('saved-meals.index'))->assertOk()->assertSee('— kcal')->assertSee('0.0 g fat');

        foreach ([$dashboard->viewData('totals'), $daily->viewData('totals'), $weekly->viewData('days')[0]['totals']] as $totals) {
            $this->assertNull($totals['energy_kcal']);
            $this->assertNull($totals['estimated_available_carbohydrate_g']);
            $this->assertSame(50.0, $totals['protein_g']);
            $this->assertSame(0.0, $totals['fat_g']);
            $this->assertSame(110.0, $totals['carbohydrate_g']);
        }
        $this->assertNull($daily->viewData('days')[0]['calories']);
        foreach ([[$dashboard, 'dn-metric', 'dn-track'], [$daily, 'pl-stat', 'pl-progress'], [$weekly, 'wp-total', 'wp-track']] as [$response, $class, $track]) {
            $cards = $this->metrics($response->getContent(), $class);
            $this->assertUnknownMetric($cards[0], $track);
            $this->assertUnknownMetric($cards[2], $track);
            $this->assertStringContainsString('class="'.$track.'"', $cards[1]);
            if ($class !== 'wp-total') {
                $this->assertCount(4, $cards);
            }
        }
        $this->view('meals.portion-guide', ['day' => ['rows' => [
            ['snapshot' => $entry->snapshot], ['snapshot' => null],
        ]]])->assertSee('This day contains — g estimated available carbs.');
        $this->assertSame($savedBefore, SavedMeal::orderBy('id')->get()->toJson());
        $this->assertSame($entriesBefore, MealPlanEntry::orderBy('id')->get()->toJson());
        Http::assertNothingSent();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_missing_null_or_invalid_totals_render_as_unknown_everywhere(): void
    {
        foreach ([[], ['totals' => null], ['totals' => 'unavailable']] as $index => $change) {
            $snapshot = array_replace($this->snapshot(), $change);
            if ($index === 0) {
                unset($snapshot['totals']);
            }
            $this->plan($snapshot, ['breakfast', 'lunch', 'dinner'][$index]);
            $this->view('meals.details', ['meal' => $snapshot])->assertSee('— kcal')->assertSee('— g protein')->assertSee('— g total carbs')->assertSee('— g fat');
        }
        $draft = $this->draft();
        foreach ([route('dashboard'), route('meal-plan.index'), route('saved-meals.index'), route('weekly-meals.show', $draft)] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertStringContainsString('— kcal', preg_replace('/\s+/u', ' ', strip_tags($response->getContent())));
        }
    }

    public static function availableCarbohydrate(): array
    {
        return [
            'stored overrides estimate' => [47, 10, 47.0],
            'stored zero overrides estimate' => [0, 10, 0.0],
            'stored without fibre' => [47, null, 47.0],
            'invalid stored falls back' => [-1, 10, 50.0],
            'text stored falls back' => ['unknown', 10, 50.0],
            'no valid source' => [null, null, null],
        ];
    }

    #[DataProvider('availableCarbohydrate')]
    public function test_available_carbohydrate_uses_the_same_basis_in_all_summaries_and_portion_guide(mixed $stored, mixed $fibre, ?float $expected): void
    {
        $snapshot = $this->snapshot(['energy_kcal' => 500, 'protein_g' => 30, 'fat_g' => 10,
            'carbohydrate_g' => 60, 'fibre_g' => $fibre, 'estimated_available_carbohydrate_g' => $stored]);
        $this->plan($snapshot);
        $draft = $this->draft();
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $daily = $this->get(route('meal-plan.index'))->assertOk();
        $weekly = $this->get(route('weekly-meals.show', $draft))->assertOk();
        foreach ([$dashboard->viewData('totals'), $daily->viewData('totals'), $weekly->viewData('days')[0]['totals']] as $totals) {
            $this->assertSame($expected, $totals['estimated_available_carbohydrate_g']);
            $this->assertSame(60.0, $totals['carbohydrate_g']);
        }
        $display = $expected === null ? '—' : number_format($expected, 1);
        $this->view('meals.portion-guide', ['day' => ['rows' => [['snapshot' => $snapshot], ['snapshot' => null]]]])
            ->assertSee('This day contains '.$display.' g estimated available carbs.');
    }

    public function test_zero_meals_and_empty_days_remain_distinct_and_render_zero_totals(): void
    {
        $draft = $this->draft();
        $this->get(route('dashboard'))->assertOk()->assertViewIs('onboarding')->assertSee('Your setup is complete');
        foreach ([route('meal-plan.index'), route('weekly-meals.show', $draft)] as $url) {
            $response = $this->get($url)->assertOk();
            $data = $response->original->getData();
            $totals = $data['totals'] ?? $data['days'][0]['totals'];
            foreach ($totals as $value) {
                $this->assertSame(0.0, $value);
            }
        }
        $this->get(route('saved-meals.index'))->assertOk();
        $this->plan($this->snapshot(['energy_kcal' => 0, 'protein_g' => '0', 'carbohydrate_g' => 0, 'fat_g' => 0, 'fibre_g' => 0]));
        foreach ([route('dashboard'), route('meal-plan.index'), route('saved-meals.index'), route('weekly-meals.show', $draft)] as $url) {
            $response = $this->get($url)->assertOk();
            $text = preg_replace('/\s+/u', ' ', strip_tags($response->getContent()));
            $this->assertStringContainsString('0 kcal', $text);
            $this->assertStringContainsString('0.0 g protein', $text);
        }
        $this->view('meals.portion-guide', ['day' => ['rows' => []]])->assertDontSee('This day contains');
    }

    public function test_replacement_preview_aggregates_proposed_snapshots_and_old_pattern_entries_are_counted_in_fill(): void
    {
        $this->plan($this->snapshot(['energy_kcal' => null, 'protein_g' => 10]), 'snack_2');
        $fill = $this->get(route('weekly-meals.show', $this->draft()))->assertOk();
        $this->assertNull($fill->viewData('days')[0]['totals']['energy_kcal']);
        $this->assertSame(10.0, $fill->viewData('days')[0]['totals']['protein_g']);
        $replacement = $this->draft(['mode' => 'replace', 'proposals' => [[
            'date' => '2026-10-26', 'slot' => 'breakfast',
            'snapshot' => $this->snapshot(['energy_kcal' => 123, 'protein_g' => null, 'carbohydrate_g' => 40, 'fibre_g' => 5, 'fat_g' => 0]),
        ]]]);
        $response = $this->get(route('weekly-meals.show', $replacement))->assertOk();
        $totals = $response->viewData('days')[0]['totals'];
        $this->assertSame(123.0, $totals['energy_kcal']);
        $this->assertNull($totals['protein_g']);
        $this->assertSame(35.0, $totals['estimated_available_carbohydrate_g']);
    }
}
