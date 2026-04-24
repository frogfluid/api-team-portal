<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Widen from unsignedSmallInteger (max 65535) to unsignedInteger
            // to prevent overflow for edge cases (e.g. forgotten clock-outs)
            $table->unsignedInteger('work_duration_minutes')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('work_duration_minutes')->default(0)->change();
        });
    }
};
