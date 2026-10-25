<?php

namespace App\Services\Nutrition;

use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;

class FoodPreferenceGroups
{
    /** Merge whole groups, keeping immutable snapshot keys and each user's choices. */
    public function link(Ingredient $source, Ingredient $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Use the same user-first lock order as preference updates. Linking is
            // an infrequent admin operation and must not race a user's choice.
            DB::table('users')->orderBy('id')->lockForUpdate()->get(['id']);
            $foods = Ingredient::query()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $foods->get($source->id);
            $target = $foods->get($target->id);
            abort_unless($source && $target, 422, 'A food selected for linking no longer exists.');
            $from = $source->preferenceFoodKey();
            $to = $target->preferenceFoodKey();

            if ($from === $to) {
                return;
            }

            $priority = ['neutral' => 0, 'preferred' => 1, 'excluded' => 2];
            $choices = DB::table('user_food_preferences')->whereIn('food_key', [$from, $to])
                ->lockForUpdate()->get()->groupBy('user_id');
            foreach ($choices as $userId => $rows) {
                $winner = $rows->sortByDesc(fn ($row) => $priority[$row->preference])->first();
                DB::table('user_food_preferences')->updateOrInsert([
                    'user_id' => $userId, 'food_key' => $to,
                ], [
                    'preference' => $winner->preference,
                    'created_at' => $rows->firstWhere('food_key', $to)?->created_at ?? $winner->created_at,
                    'updated_at' => now(),
                ]);
            }

            Ingredient::query()->where('preference_group_key', $from)->update([
                'preference_group_key' => $to,
                'preference_food' => trim((string) $target->preference_food) ?: $target->name,
            ]);
            DB::table('user_food_preferences')->where('food_key', $from)->delete();
        });
    }
}
