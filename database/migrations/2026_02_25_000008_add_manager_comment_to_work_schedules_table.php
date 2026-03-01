<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('work_schedules', 'manager_comment')) {
                $table->text('manager_comment')->nullable()->after('note');
            }

            if (! Schema::hasColumn('work_schedules', 'manager_comment_by')) {
                $table->foreignId('manager_comment_by')
                    ->nullable()
                    ->after('manager_comment')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('work_schedules', 'manager_comment_by')) {
                $table->dropConstrainedForeignId('manager_comment_by');
            }

            if (Schema::hasColumn('work_schedules', 'manager_comment')) {
                $table->dropColumn('manager_comment');
            }
        });
    }
};
