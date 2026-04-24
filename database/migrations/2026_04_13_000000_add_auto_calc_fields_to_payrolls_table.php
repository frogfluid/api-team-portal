<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->integer('actual_work_days')->default(0)->after('status');
            $table->decimal('calculated_base_salary', 10, 2)->default(0)->after('actual_work_days');
            $table->decimal('admin_deductions', 10, 2)->default(0)->after('calculated_base_salary');
            $table->decimal('admin_bonuses', 10, 2)->default(0)->after('admin_deductions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'actual_work_days',
                'calculated_base_salary',
                'admin_deductions',
                'admin_bonuses'
            ]);
        });
    }
};
