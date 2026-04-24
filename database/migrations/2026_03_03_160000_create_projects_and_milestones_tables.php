<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6366F1'); // hex color
            $table->string('icon', 16)->default('📁');       // emoji
            $table->string('status')->default('active');      // active, completed, archived
            $table->string('priority')->default('medium');    // low, medium, high, urgent
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0); // 0-100, auto-calculated
            $table->timestamps();

            $table->index(['status', 'owner_id']);
            $table->index('created_by');
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('open'); // open, completed
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        // Add project_id + milestone_id to tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->foreignId('milestone_id')->nullable()->after('project_id')->constrained()->nullOnDelete();

            $table->index('project_id');
            $table->index('milestone_id');
        });

        // Project members pivot table
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner, manager, member
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('milestone_id');
        });

        Schema::dropIfExists('project_members');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('projects');
    }
};
