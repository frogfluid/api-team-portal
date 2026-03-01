<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method_type', 30)->default('bank_transfer'); // bank_transfer, paypal, other
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('account_type', 30)->nullable(); // 普通, 当座, etc.
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->json('extra_info')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
