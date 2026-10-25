<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('shopping_ingredient_id')
                ->nullable()
                ->constrained('ingredients')
                ->nullOnDelete();

            $table->decimal('shopping_multiplier', 12, 6)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['shopping_ingredient_id']);

            $table->dropColumn([
                'shopping_ingredient_id',
                'shopping_multiplier',
            ]);
        });
    }
};