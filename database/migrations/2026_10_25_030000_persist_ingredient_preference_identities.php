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
            // Snapshot provenance stays immutable when preference groups merge.
            $table->string('preference_key', 64)->nullable();
            $table->string('preference_group_key', 64)->nullable()->index();
        });

        // Freeze precisely the existing grouping, without guessing new links or
        // rewriting any user preferences or saved/planned snapshots.
        DB::table('ingredients')->orderBy('id')->chunkById(200, function ($ingredients) {
            $normalise = fn ($value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
            foreach ($ingredients as $ingredient) {
                $name = trim((string) $ingredient->preference_food) ?: $ingredient->name;
                $key = hash('sha256', json_encode([
                    $normalise($ingredient->category), $normalise($name),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                DB::table('ingredients')->where('id', $ingredient->id)->update([
                    'preference_key' => $key, 'preference_group_key' => $key,
                ]);
            }
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('preference_key', 64)->nullable(false)->change();
            $table->string('preference_group_key', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('ingredients')->exists()) {
            throw new RuntimeException('Cannot remove stable preference identities while ingredients exist. Doing so could bypass existing exclusions.');
        }

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['preference_key', 'preference_group_key']);
        });
    }
};
