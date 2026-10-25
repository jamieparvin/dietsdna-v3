<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Services\Nutrition\FoodPreferenceGroups;
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
            'Eggs' => [
                'Eggs, whole, raw',
            ],
        ];

        DB::transaction(function () use ($groups) {
            DB::table('users')->orderBy('id')->lockForUpdate()->get(['id']);
            $ingredients = Ingredient::query()->orderBy('id')->lockForUpdate()->get();
            foreach ($groups as $food => $names) {
                // These are explicit curated names, never fuzzy matches.
                $members = $ingredients->whereIn('name', $names);
                $target = $members->first();
                if (!$target) {
                    continue;
                }
                foreach ($members->skip(1) as $member) {
                    app(FoodPreferenceGroups::class)->link($member, $target);
                }
                $target->refresh();
                Ingredient::query()->where('preference_group_key', $target->preferenceFoodKey())
                    ->update(['preference_food' => $food]);
            }
        });

        $this->command?->info('Food choices grouped and existing preferences preserved.');
    }
}
