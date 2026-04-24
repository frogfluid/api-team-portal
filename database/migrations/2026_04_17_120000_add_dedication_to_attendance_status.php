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

        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN status ENUM('normal','late','early_leave','absent','on_leave','rest_day','dedication') NOT NULL DEFAULT 'normal'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $remaining = DB::table('attendance_records')->where('status', 'dedication')->count();
        if ($remaining > 0) {
            throw new \RuntimeException("Cannot rollback: {$remaining} attendance_records still have status='dedication'. Reassign them before rolling back.");
        }

        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN status ENUM('normal','late','early_leave','absent','rest_day','on_leave') NOT NULL DEFAULT 'normal'");
    }
};
