<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('year'); // e.g., 2026

            // Annual Leave (Days)
            $table->decimal('annual_total', 5, 2)->default(0);
            $table->decimal('annual_used', 5, 2)->default(0);

            // Sick Leave (Days)
            $table->decimal('sick_total', 5, 2)->default(0);
            $table->decimal('sick_used', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_quotas');
    }
};
