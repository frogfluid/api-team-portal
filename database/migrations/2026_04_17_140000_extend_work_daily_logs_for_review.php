<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Extend work_daily_logs with detailed remote-work reporting fields
     * and the 4-action review workflow (approve / flag / reject / return).
     */
    public function up(): void
    {
        Schema::table('work_daily_logs', function (Blueprint $table) {
            $table->text('deliverables')->nullable()->after('note');
            $table->json('time_blocks')->nullable()->after('deliverables');
            $table->text('communication_log')->nullable()->after('time_blocks');

            $table->enum('review_status', ['pending', 'approved', 'flagged', 'rejected', 'returned'])
                ->default('pending')
                ->after('submitted_at');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('review_status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->boolean('is_revised')->default(false)->after('review_note');

            $table->index(['review_status', 'work_date'], 'ix_work_daily_logs_review_status_date');
        });
    }

    public function down(): void
    {
        Schema::table('work_daily_logs', function (Blueprint $table) {
            // Drop FK first (best-effort — name follows Laravel default convention)
            try {
                $table->dropForeign(['reviewed_by']);
            } catch (\Throwable $e) {
                // ignored for SQLite or environments without FK enforcement
            }

            try {
                $table->dropIndex('ix_work_daily_logs_review_status_date');
            } catch (\Throwable $e) {
                // ignored
            }

            $table->dropColumn([
                'deliverables',
                'time_blocks',
                'communication_log',
                'review_status',
                'reviewed_by',
                'reviewed_at',
                'review_note',
                'is_revised',
            ]);
        });
    }
};
