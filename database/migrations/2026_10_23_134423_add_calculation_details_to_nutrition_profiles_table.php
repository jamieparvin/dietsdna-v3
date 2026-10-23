<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->string('calculation_sex', 20)->nullable();
            $table->string('planning_context', 30)->nullable();
            $table->boolean('resistance_training')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_sex',
                'planning_context',
                'resistance_training',
            ]);
        });
    }
};