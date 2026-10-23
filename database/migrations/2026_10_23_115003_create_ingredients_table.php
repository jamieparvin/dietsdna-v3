<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();

            $table->string('food_code', 100)->unique();
            $table->string('name');
            $table->string('category', 100);
            $table->string('sub_category', 100);
            $table->string('preparation_state', 100)->index();

            // All nutritional values are per 100g edible portion.
            $table->decimal('energy_kcal', 10, 5)->nullable();
            $table->decimal('protein_g', 10, 5)->nullable();

            // Total carbohydrate, including dietary fibre.
            $table->decimal('carbohydrate_g', 10, 5)->nullable();

            $table->decimal('sugars_g', 10, 5)->nullable();
            $table->decimal('fat_g', 10, 5)->nullable();
            $table->decimal('saturates_g', 10, 5)->nullable();
            $table->decimal('fibre_g', 10, 5)->nullable();
            $table->decimal('salt_g', 10, 5)->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['category', 'sub_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};