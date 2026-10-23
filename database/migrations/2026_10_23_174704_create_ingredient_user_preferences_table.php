<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_user_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('preference', 20);
            $table->timestamps();

            $table->unique(['user_id', 'ingredient_id']);
            $table->index(['user_id', 'preference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_user_preferences');
    }
};