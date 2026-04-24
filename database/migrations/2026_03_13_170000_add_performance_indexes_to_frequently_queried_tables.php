<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LeaveQuotas: frequently filtered by user_id + year together
        Schema::table('leave_quotas', function (Blueprint $table) {
            $table->unique(['user_id', 'year'], 'uq_leave_quotas_user_year');
        });

        // AttendanceRecords: frequently filtered by user_id alone
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index('user_id', 'ix_attendance_records_user');
        });

        // Objectives: frequently filtered by type and status
        Schema::table('objectives', function (Blueprint $table) {
            $table->index(['type', 'status'], 'ix_objectives_type_status');
            $table->index('owner_id', 'ix_objectives_owner');
        });

        // KeyResults: frequently joined via objective_id
        Schema::table('key_results', function (Blueprint $table) {
            $table->index('objective_id', 'ix_key_results_objective');
        });

        // OkrCheckIns: frequently filtered by key_result_id and user_id
        Schema::table('okr_check_ins', function (Blueprint $table) {
            $table->index('key_result_id', 'ix_okr_check_ins_key_result');
            $table->index('user_id', 'ix_okr_check_ins_user');
        });

        // WeeklyReports: frequently filtered by status
        Schema::table('weekly_reports', function (Blueprint $table) {
            $table->index('status', 'ix_weekly_reports_status');
        });

        // WorkDailyLogs: frequently filtered by status
        Schema::table('work_daily_logs', function (Blueprint $table) {
            $table->index('status', 'ix_work_daily_logs_status');
        });

        // Channels: frequently filtered by type and name
        Schema::table('channels', function (Blueprint $table) {
            $table->index('type', 'ix_channels_type');
        });
    }

    public function down(): void
    {
        Schema::table('leave_quotas', fn (Blueprint $t) => $t->dropUnique('uq_leave_quotas_user_year'));
        Schema::table('attendance_records', fn (Blueprint $t) => $t->dropIndex('ix_attendance_records_user'));
        Schema::table('objectives', function (Blueprint $t) {
            $t->dropIndex('ix_objectives_type_status');
            $t->dropIndex('ix_objectives_owner');
        });
        Schema::table('key_results', fn (Blueprint $t) => $t->dropIndex('ix_key_results_objective'));
        Schema::table('okr_check_ins', function (Blueprint $t) {
            $t->dropIndex('ix_okr_check_ins_key_result');
            $t->dropIndex('ix_okr_check_ins_user');
        });
        Schema::table('weekly_reports', fn (Blueprint $t) => $t->dropIndex('ix_weekly_reports_status'));
        Schema::table('work_daily_logs', fn (Blueprint $t) => $t->dropIndex('ix_work_daily_logs_status'));
        Schema::table('channels', fn (Blueprint $t) => $t->dropIndex('ix_channels_type'));
    }
};
