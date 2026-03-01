<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('work_schedules', 'type')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->string('type', 20)->default('work')->after('user_id');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left blank. This migration only backfills missing schema.
    }
};
