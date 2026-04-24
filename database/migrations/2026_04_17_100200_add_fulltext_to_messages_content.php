<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TeamChat Medium tier (Wave 4): MySQL FULLTEXT index on messages.content.
 *
 * Required by the server-side TeamChat search (Wave 4 T3) to run
 * MATCH ... AGAINST queries efficiently on production MySQL.
 *
 * SQLite used in the test suite doesn't support FULLTEXT, so both up() and
 * down() gracefully no-op on non-MySQL drivers. This keeps `php artisan test`
 * green without requiring a separate MySQL test fixture.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages ADD FULLTEXT idx_messages_content_ft (content)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages DROP INDEX idx_messages_content_ft');
        }
    }
};
