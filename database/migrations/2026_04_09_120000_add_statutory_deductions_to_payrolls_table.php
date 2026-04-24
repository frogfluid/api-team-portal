<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('deduction_socso', 12, 2)->default(0);
            $table->decimal('deduction_eis', 12, 2)->default(0);
            $table->decimal('deduction_eps', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['deduction_socso', 'deduction_eis', 'deduction_eps']);
        });
    }
};
