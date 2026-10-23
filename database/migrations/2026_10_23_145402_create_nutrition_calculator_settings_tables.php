<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_calculator_settings', function (Blueprint $table) {
            $table->id();
            $table->json('parameters');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });

        Schema::create('nutrition_calculator_setting_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('revision')->unique();
            $table->json('parameters');
            $table->string('reason', 500);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        // Empty parameters use the defaults supplied by the settings service.
        DB::table('nutrition_calculator_settings')->insert([
            'id' => 1,
            'parameters' => '{}',
            'revision' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_calculator_setting_changes');
        Schema::dropIfExists('nutrition_calculator_settings');
    }
};