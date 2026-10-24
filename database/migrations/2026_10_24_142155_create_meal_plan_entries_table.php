<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('saved_meal_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('plan_date');
            $table->string('slot', 30);
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['user_id', 'plan_date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_entries');
    }
};