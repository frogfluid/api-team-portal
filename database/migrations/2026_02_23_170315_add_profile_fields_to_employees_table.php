<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('department_id');   // afterは適宜調整
            $table->string('phone', 30)->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('phone');
            $table->string('nationality', 100)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'phone', 'address', 'nationality']);
        });
    }
};
