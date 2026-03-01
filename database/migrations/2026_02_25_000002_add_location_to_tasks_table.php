<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'location')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('location')->nullable()->after('description');
                $table->index('location');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'location')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['location']);
                $table->dropColumn('location');
            });
        }
    }
};
