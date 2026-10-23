<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('preference_food')->nullable();
        });

        Schema::create('user_food_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('food_key', 64);
            $table->string('preference', 20);
            $table->timestamps();

            $table->unique(['user_id', 'food_key']);
        });

        // Initially link records with the same category and food name.
        // Preparation state is deliberately excluded.
        DB::table('ingredients')->update([
            'preference_food' => DB::raw('name'),
        ]);

        $existing = DB::table('ingredient_user_preferences as preferences')
            ->join(
                'ingredients',
                'ingredients.id',
                '=',
                'preferences.ingredient_id'
            )
            ->select(
                'preferences.user_id',
                'preferences.preference',
                'ingredients.category',
                'ingredients.preference_food'
            )
            ->get();

        $merged = [];

        foreach ($existing as $row) {
            $key = $this->foodKey(
                $row->category,
                $row->preference_food
            );

            $index = $row->user_id.':'.$key;

            // If old raw/cooked choices conflict, Dislike takes priority.
            if (
                ! isset($merged[$index])
                || $row->preference === 'excluded'
            ) {
                $merged[$index] = [
                    'user_id' => $row->user_id,
                    'food_key' => $key,
                    'preference' => $row->preference,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk(array_values($merged), 100) as $rows) {
            DB::table('user_food_preferences')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_food_preferences');

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('preference_food');
        });
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
};