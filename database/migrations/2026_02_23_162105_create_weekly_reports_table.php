<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 週の開始日（例：月曜始まりで固定推奨）
            $table->date('week_start_date');

            $table->text('summary')->nullable();         // 今週の要約
            $table->text('achievements')->nullable();    // 成果/やったこと
            $table->text('issues')->nullable();          // 困りごと/課題
            $table->text('next_week_plan')->nullable();  // 来週の予定
            $table->text('ideas')->nullable();           // 提案/やってみたいこと
            $table->text('support_needed')->nullable();  // 助けてほしいこと

            // 任意：運用を整えるなら
            $table->string('status', 20)->default('draft');
            $table->dateTime('submitted_at')->nullable();

            $table->timestamps();

            // 1人1週1件をDBで保証
            $table->unique(['user_id', 'week_start_date'], 'uq_weekly_reports_user_week');
            $table->index(['week_start_date', 'user_id'], 'ix_weekly_reports_week_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
