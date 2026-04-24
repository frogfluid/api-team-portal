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
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // 1. Drop FK (skip on SQLite — it doesn't enforce named FKs the same way)
        if (!$isSqlite) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        // 2. Drop the indexes that reference columns we're about to drop
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'work_date']);
            $table->dropIndex(['status', 'work_date']);
        });

        // 3. Add new columns
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dateTime('start_at')->after('user_id');
            $table->dateTime('end_at')->after('start_at');
        });

        // 4. Drop old columns (separate call for SQLite compatibility)
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn(['work_date', 'start_time', 'end_time']);
        });

        // 5. Re-add the FK on user_id (skip on SQLite)
        if (!$isSqlite) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
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
