<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('work_schedules', 'all_day')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->boolean('all_day')->default(false)->after('type');
                $table->index('all_day');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_schedules', 'all_day')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropIndex(['all_day']);
                $table->dropColumn('all_day');
            });
        }
    }
};
