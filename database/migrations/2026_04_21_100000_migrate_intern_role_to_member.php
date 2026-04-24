<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Collapse the deprecated 'intern' role into 'member'. After this runs the
     * UserRole enum can drop the INTERN case safely because no rows reference it.
     *
     * This is intentionally irreversible — we don't retain which users were
     * previously interns, and the enum case is removed in the same release.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'intern')
            ->update(['role' => 'member']);
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Migration 2026_04_21_100000_migrate_intern_role_to_member is irreversible: '
            . 'the INTERN role no longer exists in UserRole and prior intern assignments are not recorded.'
        );
    }
};
