<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'revoked_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->timestamp('revoked_at')->nullable()->after('content');
                $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
                $table->index('revoked_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'revoked_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex(['revoked_at']);
                $table->dropConstrainedForeignId('revoked_by');
                $table->dropColumn('revoked_at');
            });
        }
    }
};
