<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status', 20)->default('opened');
            $table->string('priority', 20)->default('normal');

            $table->unsignedSmallInteger('progress')->default(0);

            $table->dateTime('due_at')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->dateTime('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['owner_id']);
            $table->index(['last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
