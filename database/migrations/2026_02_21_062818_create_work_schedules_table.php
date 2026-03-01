<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();

            // Who
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // When
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Break & note
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->text('note')->nullable();

            // Workflow
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'work_date']);
            $table->index(['status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
