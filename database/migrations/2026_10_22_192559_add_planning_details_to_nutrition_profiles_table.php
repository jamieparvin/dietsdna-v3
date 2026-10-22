<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->decimal('target_weight_kg', 6, 2)->nullable();
            $table->unsignedTinyInteger('meals_per_day')->nullable();
            $table->unsignedSmallInteger('cooking_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'target_weight_kg',
                'meals_per_day',
                'cooking_minutes',
            ]);
        });
    }
};