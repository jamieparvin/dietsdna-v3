<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions');
            $table->unsignedSmallInteger('servings')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('ingredient_recipe', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipe_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ingredient_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity_g', 10, 2);
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['recipe_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_recipe');
        Schema::dropIfExists('recipes');
    }
};