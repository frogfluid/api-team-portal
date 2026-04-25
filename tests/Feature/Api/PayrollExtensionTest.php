<?php

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns Plan 01 columns on the payroll show endpoint', function () {
    $user = User::factory()->create();
    $payroll = Payroll::factory()->for($user)->create([
        'year_month' => '2026-04',
        'base_salary' => 5000,
        'deduction_socso' => 30,
        'deduction_eis' => 8,
        'deduction_eps' => 0,
        'deduction_pcb' => 100,
        'overtime' => 200,
        'other_deduction' => 50,
        'actual_work_days' => 22,
        'calculated_base_salary' => 5000,
        'status' => 'published',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/payroll/{$payroll->id}");

    $response->assertOk();
    $body = $response->json('payroll') ?? $response->json('data') ?? $response->json();
    expect($body['deduction_socso'])->toEqual(30);
    expect($body['deduction_eis'])->toEqual(8);
    expect($body['deduction_eps'])->toEqual(0);
    expect($body['deduction_pcb'])->toEqual(100);
    expect($body['overtime'])->toEqual(200);
    expect($body['other_deduction'])->toEqual(50);
    expect($body['actual_work_days'])->toBe(22);
    expect($body['calculated_base_salary'])->toEqual(5000);
    expect($body['gross_amount'])->toEqual(5200);  // 5000 + 0 (bonus) + 0 (allowance) + 200 (overtime)
    expect($body['total_deduction'])->toEqual(188); // 0 + 30 + 8 + 0 + 100 + 50
});
