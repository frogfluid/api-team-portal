<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monthly_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->date('target_month');
            $table->text('review');
            $table->json('goals');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'target_month']);
            $table->index('target_month');
            $table->index('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_messages');
    }
};
