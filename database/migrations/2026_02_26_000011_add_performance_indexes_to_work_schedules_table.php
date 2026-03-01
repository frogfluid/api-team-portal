<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->index(['user_id', 'start_at'], 'work_schedules_user_start_at_index');
            $table->index(['user_id', 'end_at'], 'work_schedules_user_end_at_index');
            $table->index('updated_at', 'work_schedules_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropIndex('work_schedules_user_start_at_index');
            $table->dropIndex('work_schedules_user_end_at_index');
            $table->dropIndex('work_schedules_updated_at_index');
        });
    }
};
