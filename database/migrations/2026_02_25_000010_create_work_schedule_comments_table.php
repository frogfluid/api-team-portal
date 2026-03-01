<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->json('mentioned_user_ids')->nullable();
            $table->timestamps();

            $table->index(['work_schedule_id', 'created_at'], 'ix_schedule_comments_schedule_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_comments');
    }
};
