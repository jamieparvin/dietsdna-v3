<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('snacks_per_day')
                ->default(0)
                ->after('meals_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_profiles', function (Blueprint $table) {
            $table->dropColumn('snacks_per_day');
        });
    }
};