<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_meal_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->string('status', 20)->default('queued');
            $table->string('source_signature', 64);
            $table->unsignedTinyInteger('meal_count');
            $table->json('slots');
            $table->json('generated')->nullable();
            $table->json('proposals')->nullable();
            $table->unsignedTinyInteger('completed_types')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_meal_drafts');
    }
};
