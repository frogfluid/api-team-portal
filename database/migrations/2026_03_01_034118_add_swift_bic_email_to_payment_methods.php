<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('swift_bic', 20)->nullable()->after('account_holder');
            $table->string('email', 100)->nullable()->after('swift_bic');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['swift_bic', 'email']);
        });
    }
};
