<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            // Drop indexes first
            if (DB::getDriverName() === 'sqlite') {
                $table->dropIndex(['user_id', 'work_date']);
                $table->dropIndex(['status', 'work_date']);
            } else {
                $table->dropIndex('work_schedules_user_id_work_date_index');
                $table->dropIndex('work_schedules_status_work_date_index');
            }
        });

        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dateTime('start_at')->after('user_id');
            $table->dateTime('end_at')->after('start_at');

            // Now it's safe to drop columns
            $table->dropColumn(['work_date', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->date('work_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->dropColumn(['start_at', 'end_at']);
        });
    }
};
