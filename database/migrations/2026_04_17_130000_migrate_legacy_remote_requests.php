<?php

use App\Services\LegacyRemoteRequestMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Migrate legacy work_schedules.type='remote_request' rows into the new
     * remote_work_requests table. Legacy rows are tagged (not deleted) so that
     * rollback is possible — see D11 and D17. The preceding 125000 migration
     * widens work_schedules.type from VARCHAR(20) to VARCHAR(32) so the
     * '_deprecated_remote_request' tag (26 chars) fits.
     */
    public function up(): void
    {
        (new LegacyRemoteRequestMigrator())->migrate();
    }

    public function down(): void
    {
        (new LegacyRemoteRequestMigrator())->rollback();
    }
};
