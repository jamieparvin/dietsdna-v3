<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('food_preferences_reviewed_at')->nullable();
        });

        // Empty legacy sets cannot prove that unspecified choices were saved.
        DB::table('users')->whereExists(function ($query) {
            $query->selectRaw('1')->from('user_food_preferences')
                ->whereColumn('user_food_preferences.user_id', 'users.id');
        })->update(['food_preferences_reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('food_preferences_reviewed_at');
        });
    }
};
