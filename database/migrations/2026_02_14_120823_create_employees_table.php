<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('employee_no', 50)->nullable();
            $table->string('employment_type', 30)->default('employee'); // employee/intern/contract
            $table->unsignedBigInteger('department_id')->nullable();   // 部署テーブル作るならFKにしてOK

            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();

            $table->string('status', 20)->default('active'); // active/inactive
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->unique('employee_no');
            $table->index(['employment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
