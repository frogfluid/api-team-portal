<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TeamChat Medium tier (Wave 4): per-user starred messages.
 *
 * Each row expresses "user X starred message Y at time Z".
 * The unique composite key (user_id, message_id) enforces idempotency so
 * the star endpoint can safely be called multiple times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_stars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starred_at')->useCurrent();

            $table->unique(['user_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_stars');
    }
};
