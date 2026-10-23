<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Services\Recipes\RecipeGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipeGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.recipe_model' => 'gpt-4.1-mini',
        ]);

        Http::preventStrayRequests();
    }

    private function catalog()
    {
        return collect([
            new Ingredient([
                'id' => 1,
                'food_code' => 'ALMONDS-RAW-001',
                'name' => 'Almonds',
                'category' => 'Fats',
                'sub_category' => 'Nuts',
                'preparation_state' => 'Raw',
            ]),
        ])->map(function ($ingredient) {
            $ingredient->id = 1;

            return $ingredient;
        });
    }

    private function recipe(int $ingredientId = 1): array
    {
        return [
            'name' => 'Almond snack',
            'description' => 'A simple portion of almonds.',
            'instructions' => '1. Weigh the almonds. 2. Divide into portions.',
            'servings' => 2,
            'ingredients' => [
                [
                    'ingredient_id' => $ingredientId,
                    'quantity_g' => 60,
                ],
            ],
        ];
    }

    private function fakeCompleted(array $recipes): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode(['recipes' => $recipes]),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    public function test_valid_recipe_data_is_returned(): void
    {
        $this->fakeCompleted([$this->recipe()]);

        $recipes = app(RecipeGenerator::class)->generate(
            $this->catalog(),
            1,
            'Simple snacks'
        );

        $this->assertCount(1, $recipes);
        $this->assertSame(1, $recipes[0]['ingredients'][0]['ingredient_id']);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false
            && $request['text']['format']['type'] === 'json_schema'
        );
    }

    public function test_unknown_ingredient_is_rejected(): void
    {
        $this->fakeCompleted([$this->recipe(999)]);

        $this->expectException(ValidationException::class);

        app(RecipeGenerator::class)->generate(
            $this->catalog(),
            1,
            'Simple snacks'
        );
    }

    public function test_duplicate_ingredient_is_rejected(): void
    {
        $recipe = $this->recipe();
        $recipe['ingredients'][] = $recipe['ingredients'][0];

        $this->fakeCompleted([$recipe]);

        $this->expectException(ValidationException::class);

        app(RecipeGenerator::class)->generate(
            $this->catalog(),
            1,
            'Simple snacks'
        );
    }

    public function test_incomplete_response_is_rejected(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'status' => 'incomplete',
                'output' => [],
            ]),
        ]);

        $this->expectException(ValidationException::class);

        app(RecipeGenerator::class)->generate(
            $this->catalog(),
            1,
            'Simple snacks'
        );
    }
}