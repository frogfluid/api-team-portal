<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('contract_renewal_status', 40)->nullable()->after('contract_end_date');
            $table->dateTime('contract_review_meeting_at')->nullable()->after('contract_renewal_status');
            $table->dateTime('contract_reviewed_at')->nullable()->after('contract_review_meeting_at');
            $table->text('contract_review_notes')->nullable()->after('contract_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'contract_renewal_status',
                'contract_review_meeting_at',
                'contract_reviewed_at',
                'contract_review_notes',
            ]);
        });
    }
};
