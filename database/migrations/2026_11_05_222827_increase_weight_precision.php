<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->decimal('weight_kg', 14, 9)->nullable()->change();
            $table->decimal('target_weight_kg', 14, 9)->nullable()->change();
        });
        Schema::table('weight_entries', function (Blueprint $table) {
            $table->decimal('weight_kg', 14, 9)->change();
        });
    }

    public function down(): void
    {
        foreach (['nutrition_profiles' => ['weight_kg', 'target_weight_kg'], 'weight_entries' => ['weight_kg']] as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->whereRaw('ROUND('.$column.', 2) <> '.$column)->exists()) {
                    throw new RuntimeException('Cannot reduce weight precision without losing saved values. Keep this migration and use a forward fix.');
                }
            }
        }
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->decimal('weight_kg', 6, 2)->nullable()->change();
            $table->decimal('target_weight_kg', 6, 2)->nullable()->change();
        });
        Schema::table('weight_entries', function (Blueprint $table) {
            $table->decimal('weight_kg', 5, 2)->change();
        });
    }
};
