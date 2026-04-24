<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_submission_lates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->timestamp('flagged_at');
            $table->timestamps();

            $table->unique(['user_id', 'iso_year', 'iso_week'], 'shift_submission_lates_user_week_unique');
            $table->index(['iso_year', 'iso_week'], 'shift_submission_lates_year_week_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_submission_lates');
    }
};
