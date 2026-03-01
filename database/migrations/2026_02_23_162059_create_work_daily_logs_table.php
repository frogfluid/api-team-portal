<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_daily_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('work_date');

            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            // 将来の休憩計算用（UIは今は出さない）
            $table->unsignedSmallInteger('break_minutes')->default(0);

            // 実働（保存しても良いし、毎回計算でもOK）
            $table->unsignedSmallInteger('worked_minutes')->nullable();

            // その日の簡単なメモ（週報とは別に日次粒度で残したい場合）
            $table->text('note')->nullable();

            // 任意：下書き/提出済みなど（今不要なら消してOK）
            $table->string('status', 20)->default('draft');
            $table->dateTime('submitted_at')->nullable();

            $table->timestamps();

            // 1人1日1件をDBで保証
            $table->unique(['user_id', 'work_date'], 'uq_work_daily_logs_user_date');
            $table->index(['work_date', 'user_id'], 'ix_work_daily_logs_date_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_daily_logs');
    }
};
