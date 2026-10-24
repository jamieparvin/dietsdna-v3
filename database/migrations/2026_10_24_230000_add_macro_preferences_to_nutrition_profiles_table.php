<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->string('macro_mode', 20)->default('goal');
            $table->string('macro_goal', 50)->nullable();
            $table->decimal('macro_protein_percent', 5, 2)->nullable();
            $table->decimal('macro_carbohydrate_percent', 5, 2)->nullable();
            $table->decimal('macro_fat_percent', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->dropColumn(['macro_mode', 'macro_goal', 'macro_protein_percent', 'macro_carbohydrate_percent', 'macro_fat_percent']);
        });
    }
};
