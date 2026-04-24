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
            $table->decimal('overtime', 12, 2)->default(0)->after('allowance');
            $table->decimal('deduction_pcb', 12, 2)->default(0)->after('deduction_eps');
            $table->decimal('other_deduction', 12, 2)->default(0)->after('deduction_pcb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['overtime', 'deduction_pcb', 'other_deduction']);
        });
    }
};
