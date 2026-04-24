<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TeamChat Medium tier (Wave 4): add pin + link-preview columns to messages.
 *
 * - pinned_at / pinned_by_user_id: powers channel pin board (Wave 4 T5/T6).
 * - link_metadata: cached OG-style link preview payload fetched on send
 *   (Wave 4 T7).
 * - idx_messages_channel_pinned: supports ORDER BY pinned_at DESC queries
 *   scoped to a channel in the pin board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('revoked_at');
            }
            if (! Schema::hasColumn('messages', 'pinned_by_user_id')) {
                $table->foreignId('pinned_by_user_id')
                    ->nullable()
                    ->after('pinned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('messages', 'link_metadata')) {
                $table->json('link_metadata')->nullable()->after('pinned_by_user_id');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['channel_id', 'pinned_at'], 'idx_messages_channel_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_channel_pinned');
        });

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'pinned_by_user_id')) {
                $table->dropConstrainedForeignId('pinned_by_user_id');
            }
            if (Schema::hasColumn('messages', 'link_metadata')) {
                $table->dropColumn('link_metadata');
            }
            if (Schema::hasColumn('messages', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
        });
    }
};
