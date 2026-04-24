<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('work_schedules', 'type')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE work_schedules MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'work'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('work_schedules', 'type')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE work_schedules MODIFY COLUMN type VARCHAR(20) NOT NULL DEFAULT 'work'");
        }
    }
};
