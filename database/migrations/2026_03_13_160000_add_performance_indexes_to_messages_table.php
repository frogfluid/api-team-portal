<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['channel_id', 'created_at'], 'ix_messages_channel_created');
            $table->index(['user_id', 'created_at'], 'ix_messages_user_created');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('ix_messages_channel_created');
            $table->dropIndex('ix_messages_user_created');
        });
    }
};
