<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Objectives ──────────────────────────
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['company', 'team', 'personal'])->default('personal');
            $table->string('period', 16); // Q1-2026, Q2-2026, etc.
            $table->string('department')->nullable(); // Department enum value
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('objectives')->nullOnDelete();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();

            $table->index(['period', 'type']);
            $table->index(['owner_id', 'status']);
        });

        // ── Key Results ──────────────────────────
        Schema::create('key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained('objectives')->cascadeOnDelete();
            $table->string('title');
            $table->enum('metric_type', ['percentage', 'number', 'boolean', 'currency'])->default('percentage');
            $table->decimal('start_value', 12, 2)->default(0);
            $table->decimal('target_value', 12, 2)->default(100);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->unsignedTinyInteger('weight')->default(100); // Weight within objective
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('objective_id');
        });

        // ── OKR Check-ins ──────────────────────────
        Schema::create('okr_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained('key_results')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('previous_value', 12, 2);
            $table->decimal('new_value', 12, 2);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['key_result_id', 'created_at']);
        });

        // ── Task → KR 联动 ──────────────────────────
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('key_result_id')->nullable()->after('milestone_id')
                ->constrained('key_results')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('key_result_id');
        });
        Schema::dropIfExists('okr_check_ins');
        Schema::dropIfExists('key_results');
        Schema::dropIfExists('objectives');
    }
};
