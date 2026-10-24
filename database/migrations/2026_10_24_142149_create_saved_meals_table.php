<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_hash', 64);
            $table->string('meal_type', 20);
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['user_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_meals');
    }
};