<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('work_schedules', 'leave_type')) {
                $table->string('leave_type', 20)->nullable()->after('type');
                $table->index('leave_type');
            }

            if (!Schema::hasColumn('work_schedules', 'leave_days')) {
                $table->decimal('leave_days', 5, 2)->nullable()->after('all_day');
                $table->index('leave_days');
            }
        });

        DB::table('work_schedules')
            ->where('type', 'leave')
            ->whereNull('leave_type')
            ->update([
                'leave_type' => 'annual',
            ]);
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('work_schedules', 'leave_type')) {
                $table->dropIndex(['leave_type']);
                $table->dropColumn('leave_type');
            }

            if (Schema::hasColumn('work_schedules', 'leave_days')) {
                $table->dropIndex(['leave_days']);
                $table->dropColumn('leave_days');
            }
        });
    }
};
