<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE attendance_records MODIFY status ENUM('normal','late','early_leave','absent','rest_day','on_leave') NOT NULL DEFAULT 'normal'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE attendance_records SET status = 'normal' WHERE status = 'on_leave'");
        DB::statement("ALTER TABLE attendance_records MODIFY status ENUM('normal','late','early_leave','absent','rest_day') NOT NULL DEFAULT 'normal'");
    }
};
