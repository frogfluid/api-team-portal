<?php

use App\Enums\ContractRenewalStatus;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: Employee contract dates/hours/lifecycle (Plan 01 / Task 9).
 *
 * Web migrations add these columns to `employees`:
 *   - contract_start_date, contract_end_date (date, nullable)
 *   - contract_hours_per_day, contract_hours_per_week (decimal 5,2, nullable)
 *   - contract_renewal_status (string 40, nullable), cast to ContractRenewalStatus enum
 *   - contract_review_meeting_at, contract_reviewed_at (datetime, nullable)
 *   - contract_review_notes (text, nullable)
 *
 * Also introduces:
 *   - App\Enums\ContractRenewalStatus (pending_discussion / renewed / ending)
 *   - App\Models\AppSetting (key/value settings, with contract_expiry_alert_days)
 *
 * These tests lock in the Eloquent-side contract for those columns and all
 * derived accessors on Employee.
 */

it('mass-assigns all new contract fields and persists them', function () {
    $employee = Employee::factory()->create([
        'contract_start_date'         => '2026-01-01',
        'contract_end_date'           => '2026-12-31',
        'contract_renewal_status'     => ContractRenewalStatus::PENDING_DISCUSSION->value,
        'contract_review_meeting_at'  => '2026-11-01 10:00:00',
        'contract_reviewed_at'        => '2026-11-02 09:30:00',
        'contract_review_notes'       => 'Discuss renewal terms.',
        'contract_hours_per_day'      => 8.00,
        'contract_hours_per_week'     => 40.00,
    ]);

    $fresh = $employee->fresh();

    expect($fresh->contract_start_date->toDateString())->toBe('2026-01-01');
    expect($fresh->contract_end_date->toDateString())->toBe('2026-12-31');
    expect($fresh->contract_renewal_status)->toBe(ContractRenewalStatus::PENDING_DISCUSSION);
    expect($fresh->contract_review_meeting_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00');
    expect($fresh->contract_reviewed_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 09:30:00');
    expect($fresh->contract_review_notes)->toBe('Discuss renewal terms.');
    expect((string) $fresh->contract_hours_per_day)->toBe('8.00');
    expect((string) $fresh->contract_hours_per_week)->toBe('40.00');
});

it('casts contract_renewal_status to the ContractRenewalStatus enum', function () {
    $employee = Employee::factory()->create([
        'contract_renewal_status' => 'renewed',
    ]);

    $fresh = $employee->fresh();

    expect($fresh->contract_renewal_status)->toBeInstanceOf(ContractRenewalStatus::class);
    expect($fresh->contract_renewal_status)->toBe(ContractRenewalStatus::RENEWED);
});

it('casts contract_hours_per_day and contract_hours_per_week to decimal:2 (string on read)', function () {
    $employee = Employee::factory()->create([
        'contract_hours_per_day'  => 7.5,
        'contract_hours_per_week' => 37.5,
    ]);

    $fresh = $employee->fresh();

    expect($fresh->contract_hours_per_day)->toBeString();
    expect($fresh->contract_hours_per_week)->toBeString();
    expect($fresh->contract_hours_per_day)->toBe('7.50');
    expect($fresh->contract_hours_per_week)->toBe('37.50');
});

describe('contract_status accessor', function () {
    it('returns "none" when contract_end_date is null', function () {
        $employee = Employee::factory()->create();
        expect($employee->contract_status)->toBe('none');
    });

    it('returns "expired" when contract_end_date is in the past', function () {
        $employee = Employee::factory()->create([
            'contract_end_date' => now()->subDays(5)->toDateString(),
        ]);
        expect($employee->fresh()->contract_status)->toBe('expired');
    });

    it('returns "expiring_soon" when end_date is within contract_expiry_alert_days threshold', function () {
        AppSetting::set('contract_expiry_alert_days', '14');

        $employee = Employee::factory()->create([
            'contract_end_date' => now()->addDays(7)->toDateString(),
        ]);

        expect($employee->fresh()->contract_status)->toBe('expiring_soon');
    });

    it('returns "active" when end_date is far enough in the future', function () {
        AppSetting::set('contract_expiry_alert_days', '14');

        $employee = Employee::factory()->create([
            'contract_end_date' => now()->addDays(60)->toDateString(),
        ]);

        expect($employee->fresh()->contract_status)->toBe('active');
    });

    it('returns expiring_soon at exactly the alert threshold boundary', function () {
        // Freeze time so "14 days away" is deterministic regardless of
        // when the test runs (no clock-drift / DST edge cases).
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));
        AppSetting::set('contract_expiry_alert_days', '14');

        $employee = Employee::factory()->create([
            'contract_end_date' => '2026-06-29', // exactly 14 days away → daysLeft === alertDays
        ]);

        expect($employee->fresh()->contract_status)->toBe('expiring_soon');

        Carbon::setTestNow();
    });

    it('returns active one day past the alert threshold boundary', function () {
        // threshold + 1: locks in the strictly-greater-than side of `<=`.
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));
        AppSetting::set('contract_expiry_alert_days', '14');

        $employee = Employee::factory()->create([
            'contract_end_date' => '2026-06-30', // 15 days away → daysLeft > alertDays
        ]);

        expect($employee->fresh()->contract_status)->toBe('active');

        Carbon::setTestNow();
    });
});

describe('days_until_contract_expiry accessor', function () {
    it('returns null when there is no contract_end_date', function () {
        $employee = Employee::factory()->create();
        expect($employee->days_until_contract_expiry)->toBeNull();
    });

    it('returns zero or negative when contract_end_date is in the past', function () {
        $employee = Employee::factory()->create([
            'contract_end_date' => now()->subDays(3)->toDateString(),
        ]);

        $days = $employee->fresh()->days_until_contract_expiry;
        expect($days)->toBeInt();
        expect($days)->toBeLessThanOrEqual(0);
    });

    it('returns a positive integer when contract_end_date is in the future', function () {
        $employee = Employee::factory()->create([
            'contract_end_date' => now()->addDays(10)->toDateString(),
        ]);

        $days = $employee->fresh()->days_until_contract_expiry;
        expect($days)->toBeInt();
        expect($days)->toBeGreaterThan(0);
    });
});

describe('resolved_contract_renewal_status accessor', function () {
    it('returns the enum value when contract_renewal_status is a valid enum value', function () {
        $employee = Employee::factory()->create([
            'contract_renewal_status' => ContractRenewalStatus::RENEWED->value,
        ]);

        expect($employee->fresh()->resolved_contract_renewal_status)
            ->toBe(ContractRenewalStatus::RENEWED);
    });

    it('falls back to PENDING_DISCUSSION when expiring_soon and renewal status is null', function () {
        AppSetting::set('contract_expiry_alert_days', '30');

        $employee = Employee::factory()->create([
            'contract_end_date'       => now()->addDays(10)->toDateString(),
            'contract_renewal_status' => null,
        ]);

        expect($employee->fresh()->resolved_contract_renewal_status)
            ->toBe(ContractRenewalStatus::PENDING_DISCUSSION);
    });

    it('falls back to PENDING_DISCUSSION when expired and renewal status is null', function () {
        $employee = Employee::factory()->create([
            'contract_end_date'       => now()->subDays(1)->toDateString(),
            'contract_renewal_status' => null,
        ]);

        expect($employee->fresh()->resolved_contract_renewal_status)
            ->toBe(ContractRenewalStatus::PENDING_DISCUSSION);
    });

    it('returns null for active contract with no renewal status set', function () {
        AppSetting::set('contract_expiry_alert_days', '14');

        $employee = Employee::factory()->create([
            'contract_end_date'       => now()->addDays(90)->toDateString(),
            'contract_renewal_status' => null,
        ]);

        expect($employee->fresh()->resolved_contract_renewal_status)->toBeNull();
    });
});

describe('should_notify_contract_expiry accessor', function () {
    it('returns false when there is no contract_end_date', function () {
        $employee = Employee::factory()->create();
        expect($employee->should_notify_contract_expiry)->toBeFalse();
    });

    it('returns false when there is no user relation', function () {
        // Create an Employee instance WITHOUT persisting a user relation.
        // Using a detached in-memory instance avoids FK complications.
        $employee = new Employee();
        $employee->contract_end_date = now()->addDays(10);
        // user_id not set → user relation returns null

        expect($employee->should_notify_contract_expiry)->toBeFalse();
    });

    it('returns true when expired and resolved status is PENDING_DISCUSSION', function () {
        $employee = Employee::factory()->create([
            'contract_end_date'       => now()->subDays(2)->toDateString(),
            'contract_renewal_status' => null, // resolves to PENDING_DISCUSSION via fallback
        ]);

        expect($employee->fresh()->should_notify_contract_expiry)->toBeTrue();
    });

    it('returns false when renewal status is RENEWED (final)', function () {
        $employee = Employee::factory()->create([
            'contract_end_date'       => now()->addDays(5)->toDateString(),
            'contract_renewal_status' => ContractRenewalStatus::RENEWED->value,
        ]);

        expect($employee->fresh()->should_notify_contract_expiry)->toBeFalse();
    });

    it('returns true when contract_end_date is in the future and no renewal_status is set', function () {
        AppSetting::set('contract_expiry_alert_days', '14');

        $employee = Employee::factory()->create([
            'contract_end_date'       => now()->addDays(90)->toDateString(),
            'contract_renewal_status' => null,
        ]);

        expect($employee->fresh()->should_notify_contract_expiry)->toBeTrue();
    });
});

describe('effective_contract_hours accessors', function () {
    it('returns daily and daily*5 when only daily is given', function () {
        $employee = Employee::factory()->create([
            'contract_hours_per_day'  => 8.00,
            'contract_hours_per_week' => null,
        ]);

        $fresh = $employee->fresh();
        expect($fresh->effective_contract_hours_per_day)->toBe(8.00);
        expect($fresh->effective_contract_hours_per_week)->toBe(40.00);
    });

    it('falls back per_day = weekly/5 when only weekly is given', function () {
        $employee = Employee::factory()->create([
            'contract_hours_per_day'  => null,
            'contract_hours_per_week' => 37.5,
        ]);

        $fresh = $employee->fresh();
        expect($fresh->effective_contract_hours_per_day)->toBe(7.50);
        expect($fresh->effective_contract_hours_per_week)->toBe(37.50);
    });

    it('prefers stored values when both are given', function () {
        $employee = Employee::factory()->create([
            'contract_hours_per_day'  => 8.00,
            'contract_hours_per_week' => 42.00,
        ]);

        $fresh = $employee->fresh();
        // When both set, the accessors should return the stored values directly.
        expect($fresh->effective_contract_hours_per_day)->toBe(8.00);
        expect($fresh->effective_contract_hours_per_week)->toBe(42.00);
    });

    it('returns null for both when both are null', function () {
        $employee = Employee::factory()->create();

        $fresh = $employee->fresh();
        expect($fresh->effective_contract_hours_per_day)->toBeNull();
        expect($fresh->effective_contract_hours_per_week)->toBeNull();
    });
});

describe('AppSetting model', function () {
    it('get() returns default when key is missing', function () {
        expect(AppSetting::get('nonexistent_key', 'fallback'))->toBe('fallback');
        expect(AppSetting::get('nonexistent_key'))->toBeNull();
    });

    it('set() upserts correctly (create + update)', function () {
        // Create path
        AppSetting::set('feature_x_enabled', '1');
        expect(AppSetting::get('feature_x_enabled'))->toBe('1');
        expect(AppSetting::where('key', 'feature_x_enabled')->count())->toBe(1);

        // Update path
        AppSetting::set('feature_x_enabled', '0');
        expect(AppSetting::get('feature_x_enabled'))->toBe('0');
        expect(AppSetting::where('key', 'feature_x_enabled')->count())->toBe(1);
    });
});
