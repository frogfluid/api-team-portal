<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_in_ip', 45)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            $table->unsignedSmallInteger('work_duration_minutes')->default(0);
            $table->enum('status', ['normal', 'late', 'early_leave', 'absent', 'rest_day'])->default('normal');
            $table->text('note')->nullable();
            $table->boolean('is_manual_override')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
