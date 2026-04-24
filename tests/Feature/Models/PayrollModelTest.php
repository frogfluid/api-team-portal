<?php

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: Payroll statutory deductions + payslip + auto-calc fields.
 *
 * Web migrations add these columns to `payrolls`:
 *   - deduction_socso, deduction_eis, deduction_eps (decimal 12,2)
 *   - overtime, deduction_pcb, other_deduction (decimal 12,2)
 *   - actual_work_days (int), calculated_base_salary (decimal 10,2)
 *   - admin_deductions, admin_bonuses (decimal 10,2; admin-only, NOT fillable)
 *
 * These tests lock in the Eloquent-side contract for those columns and the
 * derived accessors (gross_amount, total_deduction, hourly_rate).
 */

it('allows mass-assignment of new statutory deduction fields', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'overtime' => 150.50,
        'deduction_socso' => 24.75,
        'deduction_eis' => 7.50,
        'deduction_eps' => 550.00,
        'deduction_pcb' => 120.00,
        'other_deduction' => 30.25,
        'status' => 'draft',
    ]);

    $fresh = $payroll->fresh();

    expect((float) $fresh->overtime)->toBe(150.50);
    expect((float) $fresh->deduction_socso)->toBe(24.75);
    expect((float) $fresh->deduction_eis)->toBe(7.50);
    expect((float) $fresh->deduction_eps)->toBe(550.00);
    expect((float) $fresh->deduction_pcb)->toBe(120.00);
    expect((float) $fresh->other_deduction)->toBe(30.25);
});

it('casts numeric fields to float', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'overtime' => 100,
        'deduction_socso' => 25,
        'status' => 'draft',
    ]);

    $fresh = $payroll->fresh();

    expect($fresh->base_salary)->toBeFloat();
    expect($fresh->overtime)->toBeFloat();
    expect($fresh->deduction_socso)->toBeFloat();
});

it('casts actual_work_days to integer', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'actual_work_days' => 22,
        'status' => 'draft',
    ]);

    $fresh = $payroll->fresh();

    expect($fresh->actual_work_days)->toBeInt();
    expect($fresh->actual_work_days)->toBe(22);
});

it('does NOT mass-assign admin_deductions or admin_bonuses', function () {
    $user = User::factory()->create();

    $payroll = new Payroll();
    $payroll->fill([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'admin_deductions' => 999,
        'admin_bonuses' => 888,
        'status' => 'draft',
    ]);

    $attrs = $payroll->getAttributes();
    expect($attrs)->not->toHaveKey('admin_deductions');
    expect($attrs)->not->toHaveKey('admin_bonuses');

    // Also verify via Payroll::create() (mass-assignment path through fill()).
    // Note: Factory::create() uses Model::unguarded() and deliberately bypasses
    // fillable — so we test against the Eloquent mass-assignment API itself.
    $created = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-05',
        'base_salary' => 5000,
        'admin_deductions' => 777,
        'admin_bonuses' => 666,
        'status' => 'draft',
    ]);

    $fresh = $created->fresh();
    // DB default is 0 — values we tried to mass-assign must NOT have landed.
    expect((float) $fresh->admin_deductions)->toBe(0.0);
    expect((float) $fresh->admin_bonuses)->toBe(0.0);
});

it('gross_amount accessor sums base+bonus+allowance+overtime', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'bonus' => 500,
        'allowance' => 200,
        'overtime' => 150,
        'status' => 'draft',
    ]);

    expect($payroll->fresh()->gross_amount)->toBe(5850.00);
});

it('total_deduction accessor sums all six deduction columns', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'deduction' => 100,
        'deduction_socso' => 25,
        'deduction_eis' => 7.50,
        'deduction_eps' => 550,
        'deduction_pcb' => 120,
        'other_deduction' => 30,
        'status' => 'draft',
    ]);

    expect($payroll->fresh()->total_deduction)->toBe(832.50);
});

it('hourly_rate accessor returns base_salary / 120 rounded to 4 dp, zero when base_salary is 0', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 6000,
        'status' => 'draft',
    ]);

    expect($payroll->fresh()->hourly_rate)->toBe(50.0);

    $zero = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-05',
        'base_salary' => 0,
        'status' => 'draft',
    ]);

    expect($zero->fresh()->hourly_rate)->toBe(0.0);

    // Precision: 5000 / 120 = 41.66666... → 41.6667 (4 dp)
    $precise = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-06',
        'base_salary' => 5000,
        'status' => 'draft',
    ]);

    expect($precise->fresh()->hourly_rate)->toBe(41.6667);
});

it('calculateNetAmount returns gross_amount minus total_deduction', function () {
    $user = User::factory()->create();

    $payroll = Payroll::create([
        'user_id' => $user->id,
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'bonus' => 500,
        'allowance' => 200,
        'overtime' => 150,
        'deduction' => 100,
        'deduction_socso' => 25,
        'deduction_eis' => 7.50,
        'deduction_eps' => 550,
        'deduction_pcb' => 120,
        'other_deduction' => 30,
        'status' => 'draft',
    ]);

    $fresh = $payroll->fresh();

    // gross = 5850, deductions = 832.50 → net = 5017.50
    expect($fresh->calculateNetAmount())->toBe(5017.50);
});
