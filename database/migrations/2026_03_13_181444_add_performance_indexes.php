<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AuditLogs: frequently filtered by auditable entity and time
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id'], 'ix_audit_logs_auditable');
            $table->index('created_at', 'ix_audit_logs_created');
        });

        // AttendanceRecords: prevent duplicate clock-in per user per day
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unique(['user_id', 'date'], 'uq_attendance_records_user_date');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('ix_audit_logs_auditable');
            $table->dropIndex('ix_audit_logs_created');
        });
        Schema::table('attendance_records', fn (Blueprint $t) => $t->dropUnique('uq_attendance_records_user_date'));
    }
};
