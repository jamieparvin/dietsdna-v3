<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoodPreferenceGroupingSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Beef' => [
                'Beef, lean, raw',
                'Beef topside, lean',
            ],
            'Beef mince, extra lean' => [
                'Beef mince, extra lean, raw',
                'Beef mince, extra lean, stewed',
            ],
            'Chicken breast' => [
                'Chicken breast, raw',
                'Chicken breast, cooked',
            ],
            'Chicken thigh' => [
                'Chicken thigh, raw',
                'Chicken thigh, casseroled',
            ],
            'Duck' => [
                'Duck, raw',
                'Duck, roasted',
            ],
            'Lamb' => [
                'Lamb, raw',
                'Lamb leg, roasted, lean',
            ],
            'Lamb mince' => [
                'Lamb mince, raw',
                'Lamb mince, stewed',
            ],
            'Pork mince' => [
                'Pork mince, raw',
                'Pork mince, stewed',
            ],
            'Quail' => [
                'Quail, raw',
            ],
            'Turkey breast' => [
                'Turkey breast, raw',
                'Turkey breast fillet, grilled',
            ],
            'Turkey mince' => [
                'Turkey mince, raw',
                'Turkey mince, stewed',
            ],
            'Venison' => [
                'Venison, raw',
                'Venison, roasted',
            ],
            'Sweet potatoes' => [
                'Sweet potatoes',
                'Sweet potatoes, peeled',
            ],
            'White potatoes' => [
                'White potatoes, flesh and skin',
                'White potatoes, peeled',
            ],
            'Coconut' => [
                'Coconut, raw',
            ],
            'Asparagus' => ['Asparagus, raw'],
            'Aubergine' => ['Aubergine, raw'],
            'Beetroot' => ['Beetroot, raw'],
            'Broad beans' => ['Broad beans, shelled, raw'],
            'Broccoli' => ['Broccoli, raw'],
            'Brussels sprouts' => ['Brussels sprouts, raw'],
            'Butternut squash' => ['Butternut squash, raw'],
            'Cabbage' => ['Cabbage, raw'],
            'Carrots' => ['Carrots, raw'],
            'Cauliflower' => ['Cauliflower, raw'],
            'Celery' => ['Celery, raw'],
            'Courgette' => ['Courgette, raw'],
            'Cucumber' => ['Cucumber, with peel, raw'],
            'Green beans' => ['Green beans, raw'],
            'Kale' => ['Kale, raw'],
            'Leeks' => ['Leeks, raw'],
            'Lettuce, green leaf' => ['Lettuce, green leaf, raw'],
            'Mushrooms, white' => ['Mushrooms, white, raw'],
            'Onions' => ['Onions, raw'],
            'Pak choi' => ['Pak choi, raw'],
            'Parsnips' => ['Parsnips, raw'],
            'Peppers, sweet red' => ['Peppers, sweet red, raw'],
            'Pumpkin' => ['Pumpkin, raw'],
            'Radishes' => ['Radishes, raw'],
            'Rocket' => ['Rocket, raw'],
            'Runner beans' => ['Runner beans, raw'],
            'Spinach' => ['Spinach, raw'],
            'Spring onions' => ['Spring onions, raw'],
            'Sugar snap peas/mangetout' => [
                'Sugar snap peas/mangetout, raw',
            ],
            'Swede' => ['Swede, raw'],
            'Sweetcorn, yellow' => ['Sweetcorn, yellow, raw'],
            'Tomatoes, red, ripe' => ['Tomatoes, red, ripe, raw'],
            'Watercress' => ['Watercress, raw'],
            'Tofu' => [
                'Tofu, firm, raw',
            ],
            'Tempeh' => [
                'Tempeh, unprepared',
            ],
            'Chickpeas' => [
                'Chickpeas, cooked, boiled, unsalted',
            ],
            'Lentils' => [
                'Lentils, cooked, boiled, unsalted',
            ],
            'Kidney beans' => [
                'Kidney beans, cooked, boiled, unsalted',
            ],
            'Green peas' => [
                'Green peas, cooked, boiled, drained, unsalted',
            ],
            'Cottage cheese' => [
                'Cottage cheese, plain, 2% milkfat',
            ],
            'Greek yogurt' => [
                'Greek yogurt, plain, nonfat (0% fat)',
            ],
        ];

        DB::transaction(function () use ($groups) {
            // Match the preference controller's user locking.
            DB::table('users')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $ingredients = DB::table('ingredients')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $nameMap = [];

            foreach ($groups as $food => $names) {
                foreach ($names as $name) {
                    $nameMap[$name] = $food;
                }
            }

            $keyMap = [];

            foreach ($ingredients as $ingredient) {
                if (! isset($nameMap[$ingredient->name])) {
                    continue;
                }

                $oldName = trim((string) $ingredient->preference_food)
                    ?: $ingredient->name;

                $newName = $nameMap[$ingredient->name];

                $oldKey = $this->foodKey(
                    $ingredient->category,
                    $oldName
                );

                $newKey = $this->foodKey(
                    $ingredient->category,
                    $newName
                );

                $keyMap[$oldKey] = $newKey;

                DB::table('ingredients')
                    ->where('id', $ingredient->id)
                    ->update(['preference_food' => $newName]);
            }

            $preferences = DB::table('user_food_preferences')
                ->lockForUpdate()
                ->get();

            $merged = [];
            $priority = [
                'neutral' => 0,
                'preferred' => 1,
                'excluded' => 2,
            ];

            foreach ($preferences as $preference) {
                $key = $keyMap[$preference->food_key]
                    ?? $preference->food_key;

                $index = $preference->user_id.':'.$key;

                if (
                    ! isset($merged[$index])
                    || $priority[$preference->preference]
                        > $priority[$merged[$index]['preference']]
                ) {
                    $merged[$index] = [
                        'user_id' => $preference->user_id,
                        'food_key' => $key,
                        'preference' => $preference->preference,
                        'created_at' => $preference->created_at,
                        'updated_at' => $preference->updated_at,
                    ];
                }
            }

            // Write merged choices before removing obsolete keys.
            foreach (array_chunk(array_values($merged), 100) as $rows) {
                DB::table('user_food_preferences')->upsert(
                    $rows,
                    ['user_id', 'food_key'],
                    ['preference', 'updated_at']
                );
            }

            $obsoleteKeys = array_diff(
                array_keys($keyMap),
                array_values($keyMap)
            );

            if ($obsoleteKeys) {
                DB::table('user_food_preferences')
                    ->whereIn('food_key', $obsoleteKeys)
                    ->delete();
            }
        });

        $this->command->info(
            'Food choices grouped and existing preferences preserved.'
        );
    }

    private function foodKey(?string $category, string $name): string
    {
        $normalise = fn ($value) => mb_strtolower(
            preg_replace('/\s+/u', ' ', trim((string) $value))
        );

        return hash('sha256', json_encode([
            $normalise($category),
            $normalise($name),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}