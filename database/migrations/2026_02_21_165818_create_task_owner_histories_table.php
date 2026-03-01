<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_owner_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('from_owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('to_owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('changed_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('note')->nullable();

            $table->dateTime('changed_at');

            $table->timestamps();

            $table->index(['task_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_owner_histories');
    }
};
