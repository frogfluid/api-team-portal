<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->uuid('repeat_group_id')->nullable()->after('note');
            $table->index('repeat_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropIndex(['repeat_group_id']);
            $table->dropColumn('repeat_group_id');
        });
    }
};
