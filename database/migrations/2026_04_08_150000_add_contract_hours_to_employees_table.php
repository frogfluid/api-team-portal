<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->decimal('contract_hours_per_day', 5, 2)->nullable()->after('contract_end_date');
            $table->decimal('contract_hours_per_week', 5, 2)->nullable()->after('contract_hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['contract_hours_per_day', 'contract_hours_per_week']);
        });
    }
};
