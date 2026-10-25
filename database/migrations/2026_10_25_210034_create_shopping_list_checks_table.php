<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('week_start');

            // Identifies the ingredient and preparation being purchased.
            $table->char('item_key', 64);

            // Records the quantity covered by this purchased-item tick.
            $table->decimal('quantity_g', 14, 2);

            $table->timestamps();

            $table->unique(
                ['user_id', 'week_start', 'item_key'],
                'shopping_checks_user_week_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_checks');
    }
};