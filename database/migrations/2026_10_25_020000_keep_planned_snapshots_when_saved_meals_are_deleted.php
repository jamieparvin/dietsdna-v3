<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->dropForeign(['saved_meal_id']);
        });
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreignId('saved_meal_id')->nullable()->change();
        });
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreign('saved_meal_id')->references('id')->on('saved_meals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Do not delete planned snapshots just to restore a NOT NULL constraint.
        if (DB::table('meal_plan_entries')->whereNull('saved_meal_id')->exists()) {
            throw new RuntimeException('Cannot restore the old saved-meal constraint while planned meals have no saved option. Restore those links before rolling this migration back.');
        }
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->dropForeign(['saved_meal_id']);
        });
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreignId('saved_meal_id')->nullable(false)->change();
        });
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreign('saved_meal_id')->references('id')->on('saved_meals')->restrictOnDelete();
        });
    }
};
