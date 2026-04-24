<?php

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Schema parity (Plan 01 / Task 13).
 *
 * Verifies that the api-team-portal testing DB, after a fresh migration, contains:
 *   1. every table the web/app project expects; and
 *   2. every new column introduced across Plan 01 tasks 5–11 on its respective table.
 *
 * Task 12 (AppSetting seed) is also asserted here, since the data check belongs
 * with the schema check: no standalone "data parity" test is warranted for a
 * single seeded row.
 */
it('every expected table exists', function () {
    $expected = [
        'users', 'employees', 'tasks', 'projects', 'milestones',
        'objectives', 'key_results', 'okr_check_ins',
        'attendance_records', 'work_schedules', 'work_schedule_comments',
        'work_daily_logs', 'weekly_reports',
        'messages', 'channels', 'message_stars', 'message_reactions',
        'announcements', 'knowledge_documents', 'knowledge_comments',
        'work_deliverables',
        'ai_evaluations', 'job_scopes', 'job_scope_user',
        'remote_work_requests', 'shift_submission_lates',
        'monthly_messages', 'monthly_message_comments',
        'cleaning_duties', 'app_settings', 'audit_logs',
        'payrolls', 'payment_methods',
        'notifications', 'personal_access_tokens',
    ];

    foreach ($expected as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table: {$table}");
    }
});

it('every new column exists on its table', function () {
    // Task 5 — User: free-form responsibilities field.
    expect(Schema::hasColumn('users', 'responsibilities'))->toBeTrue('users.responsibilities');

    // Task 6 — Message: pin + link metadata. (The plan's 'medium' column was
    // a placeholder; the real additions are these three.)
    expect(Schema::hasColumn('messages', 'pinned_at'))->toBeTrue('messages.pinned_at');
    expect(Schema::hasColumn('messages', 'pinned_by_user_id'))->toBeTrue('messages.pinned_by_user_id');
    expect(Schema::hasColumn('messages', 'link_metadata'))->toBeTrue('messages.link_metadata');

    // Task 7 — AttendanceRecord: auto-clock-out flag.
    expect(Schema::hasColumn('attendance_records', 'is_auto_clocked_out'))
        ->toBeTrue('attendance_records.is_auto_clocked_out');

    // Task 8 — Payroll: Malaysian statutory deductions + auto-calc fields.
    expect(Schema::hasColumn('payrolls', 'deduction_socso'))->toBeTrue('payrolls.deduction_socso');
    expect(Schema::hasColumn('payrolls', 'deduction_eis'))->toBeTrue('payrolls.deduction_eis');
    expect(Schema::hasColumn('payrolls', 'deduction_eps'))->toBeTrue('payrolls.deduction_eps');
    expect(Schema::hasColumn('payrolls', 'deduction_pcb'))->toBeTrue('payrolls.deduction_pcb');
    expect(Schema::hasColumn('payrolls', 'overtime'))->toBeTrue('payrolls.overtime');
    expect(Schema::hasColumn('payrolls', 'other_deduction'))->toBeTrue('payrolls.other_deduction');
    expect(Schema::hasColumn('payrolls', 'actual_work_days'))->toBeTrue('payrolls.actual_work_days');
    expect(Schema::hasColumn('payrolls', 'calculated_base_salary'))->toBeTrue('payrolls.calculated_base_salary');
    expect(Schema::hasColumn('payrolls', 'admin_deductions'))->toBeTrue('payrolls.admin_deductions');
    expect(Schema::hasColumn('payrolls', 'admin_bonuses'))->toBeTrue('payrolls.admin_bonuses');

    // Task 9 — Employee contract lifecycle.
    expect(Schema::hasColumn('employees', 'contract_start_date'))->toBeTrue('employees.contract_start_date');
    expect(Schema::hasColumn('employees', 'contract_end_date'))->toBeTrue('employees.contract_end_date');
    expect(Schema::hasColumn('employees', 'contract_hours_per_day'))->toBeTrue('employees.contract_hours_per_day');
    expect(Schema::hasColumn('employees', 'contract_hours_per_week'))->toBeTrue('employees.contract_hours_per_week');
    expect(Schema::hasColumn('employees', 'contract_renewal_status'))->toBeTrue('employees.contract_renewal_status');
    expect(Schema::hasColumn('employees', 'contract_review_meeting_at'))->toBeTrue('employees.contract_review_meeting_at');
    expect(Schema::hasColumn('employees', 'contract_reviewed_at'))->toBeTrue('employees.contract_reviewed_at');
    expect(Schema::hasColumn('employees', 'contract_review_notes'))->toBeTrue('employees.contract_review_notes');

    // Task 10 — WorkDailyLog review workflow.
    expect(Schema::hasColumn('work_daily_logs', 'deliverables'))->toBeTrue('work_daily_logs.deliverables');
    expect(Schema::hasColumn('work_daily_logs', 'time_blocks'))->toBeTrue('work_daily_logs.time_blocks');
    expect(Schema::hasColumn('work_daily_logs', 'communication_log'))->toBeTrue('work_daily_logs.communication_log');
    expect(Schema::hasColumn('work_daily_logs', 'review_status'))->toBeTrue('work_daily_logs.review_status');
    expect(Schema::hasColumn('work_daily_logs', 'reviewed_by'))->toBeTrue('work_daily_logs.reviewed_by');
    expect(Schema::hasColumn('work_daily_logs', 'reviewed_at'))->toBeTrue('work_daily_logs.reviewed_at');
    expect(Schema::hasColumn('work_daily_logs', 'review_note'))->toBeTrue('work_daily_logs.review_note');
    expect(Schema::hasColumn('work_daily_logs', 'is_revised'))->toBeTrue('work_daily_logs.is_revised');

    // Task 11 — WorkSchedule remote flag.
    expect(Schema::hasColumn('work_schedules', 'is_remote'))->toBeTrue('work_schedules.is_remote');

    // Task 12 — AppSetting seeded value (data, but asserted here alongside the
    // rest of the schema-parity checks).
    expect(AppSetting::get('contract_expiry_alert_days'))
        ->not->toBeNull('app_settings seed: contract_expiry_alert_days');
});
