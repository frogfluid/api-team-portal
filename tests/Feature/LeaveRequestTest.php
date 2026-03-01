<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LeaveQuota;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::MEMBER->value,
            'is_active' => true,
            'preferences' => [
                'workspace' => [
                    'timezone' => 'UTC',
                ],
            ],
        ], $overrides));
    }

    public function test_leave_page_is_accessible(): void
    {
        $member = $this->createMember();

        $response = $this->actingAs($member)->get(route('app.leaves.index'));

        $response->assertOk();
        $response->assertSee('Leave Hub');
    }

    public function test_admin_is_redirected_to_review_center_for_leave_management(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('app.leaves.index'));

        $response->assertRedirect(route('app.reviews.index'));
    }

    public function test_member_can_create_leave_request(): void
    {
        $member = $this->createMember();
        LeaveQuota::create([
            'user_id' => $member->id,
            'year' => (int) now('UTC')->year,
            'annual_total' => 10,
            'annual_used' => 0,
            'sick_total' => 5,
            'sick_used' => 0,
        ]);

        $response = $this->actingAs($member)->post(route('app.leaves.store'), [
            'leave_type' => 'annual',
            'start_date' => now('UTC')->addDay()->toDateString(),
            'end_date' => now('UTC')->addDays(3)->toDateString(),
            'note' => 'Family trip',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect(route('app.leaves.index'));
        $this->assertDatabaseHas('work_schedules', [
            'user_id' => $member->id,
            'type' => 'leave',
            'leave_type' => 'annual',
            'leave_days' => 3.00,
            'status' => 'pending',
            'all_day' => 1,
        ]);
    }

    public function test_member_cannot_create_leave_for_past_date(): void
    {
        $member = $this->createMember();

        $response = $this->actingAs($member)->post(route('app.leaves.store'), [
            'leave_type' => 'annual',
            'start_date' => now('UTC')->subDay()->toDateString(),
            'end_date' => now('UTC')->subDay()->toDateString(),
            'timezone' => 'UTC',
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_member_without_assigned_quota_cannot_create_leave_request(): void
    {
        $member = $this->createMember();

        $response = $this->actingAs($member)->post(route('app.leaves.store'), [
            'leave_type' => 'annual',
            'start_date' => now('UTC')->addDay()->toDateString(),
            'end_date' => now('UTC')->addDay()->toDateString(),
            'timezone' => 'UTC',
        ]);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseMissing('work_schedules', [
            'user_id' => $member->id,
            'type' => 'leave',
        ]);
    }

    public function test_admin_cannot_create_leave_request(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('app.leaves.store'), [
            'leave_type' => 'annual',
            'start_date' => now('UTC')->addDay()->toDateString(),
            'end_date' => now('UTC')->addDay()->toDateString(),
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(403);
    }

    public function test_updating_approved_leave_rolls_back_used_quota_and_resubmits(): void
    {
        $member = $this->createMember();
        $year = (int) now('UTC')->year;

        $quota = LeaveQuota::create([
            'user_id' => $member->id,
            'year' => $year,
            'annual_total' => 14,
            'annual_used' => 5,
            'sick_total' => 10,
            'sick_used' => 0,
        ]);

        $leave = WorkSchedule::create([
            'user_id' => $member->id,
            'type' => 'leave',
            'leave_type' => 'annual',
            'leave_days' => 2,
            'all_day' => true,
            'start_at' => now('UTC')->addDay()->startOfDay(),
            'end_at' => now('UTC')->addDays(2)->endOfDay(),
            'status' => 'approved',
            'approved_by' => $member->id,
            'approved_at' => now('UTC'),
        ]);

        $response = $this->actingAs($member)->put(route('app.leaves.update', $leave), [
            'leave_type' => 'sick',
            'start_date' => now('UTC')->addDay()->toDateString(),
            'end_date' => now('UTC')->addDay()->toDateString(),
            'note' => 'Medical visit',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect(route('app.leaves.index'));
        $this->assertDatabaseHas('work_schedules', [
            'id' => $leave->id,
            'leave_type' => 'sick',
            'leave_days' => 1.00,
            'status' => 'pending',
            'approved_by' => null,
        ]);

        $quota->refresh();
        $this->assertSame('3.00', $quota->annual_used);
        $this->assertSame('0.00', $quota->sick_used);
    }

    public function test_cancelling_approved_future_leave_restores_quota(): void
    {
        $member = $this->createMember();
        $year = (int) now('UTC')->year;

        $quota = LeaveQuota::create([
            'user_id' => $member->id,
            'year' => $year,
            'annual_total' => 14,
            'annual_used' => 4,
            'sick_total' => 10,
            'sick_used' => 0,
        ]);

        $leave = WorkSchedule::create([
            'user_id' => $member->id,
            'type' => 'leave',
            'leave_type' => 'annual',
            'leave_days' => 2,
            'all_day' => true,
            'start_at' => now('UTC')->addDay()->startOfDay(),
            'end_at' => now('UTC')->addDays(2)->endOfDay(),
            'status' => 'approved',
            'approved_by' => $member->id,
            'approved_at' => now('UTC'),
        ]);

        $response = $this->actingAs($member)->delete(route('app.leaves.destroy', $leave));

        $response->assertRedirect(route('app.leaves.index'));
        $this->assertDatabaseMissing('work_schedules', ['id' => $leave->id]);
        $quota->refresh();
        $this->assertSame('2.00', $quota->annual_used);
    }

    public function test_past_leave_request_cannot_be_deleted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-26 08:00:00', 'UTC'));

        try {
            $member = $this->createMember();
            $leave = WorkSchedule::create([
                'user_id' => $member->id,
                'type' => 'leave',
                'leave_type' => 'annual',
                'leave_days' => 1,
                'all_day' => true,
                'start_at' => Carbon::parse('2026-02-25 00:00:00', 'UTC'),
                'end_at' => Carbon::parse('2026-02-25 23:59:59', 'UTC'),
                'status' => 'pending',
            ]);

            $response = $this->actingAs($member)->delete(route('app.leaves.destroy', $leave), [
                'timezone' => 'UTC',
            ]);

            $response->assertRedirect(route('app.leaves.index'));
            $this->assertDatabaseHas('work_schedules', ['id' => $leave->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manager_approval_deducts_matching_leave_type_quota(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::MANAGER->value,
            'is_active' => true,
        ]);
        $member = $this->createMember();
        $year = (int) now('UTC')->year;

        $quota = LeaveQuota::create([
            'user_id' => $member->id,
            'year' => $year,
            'annual_total' => 14,
            'annual_used' => 0,
            'sick_total' => 10,
            'sick_used' => 1,
        ]);

        $leave = WorkSchedule::create([
            'user_id' => $member->id,
            'type' => 'leave',
            'leave_type' => 'sick',
            'leave_days' => 2,
            'all_day' => true,
            'start_at' => now('UTC')->addDay()->startOfDay(),
            'end_at' => now('UTC')->addDays(2)->endOfDay(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)->post(route('app.reviews.schedules.approve', ['schedule' => $leave->id]));

        $response->assertRedirect();
        $this->assertDatabaseHas('work_schedules', [
            'id' => $leave->id,
            'status' => 'approved',
        ]);
        $quota->refresh();
        $this->assertSame('3.00', $quota->sick_used);
        $this->assertSame('0.00', $quota->annual_used);
    }

    public function test_manager_cannot_approve_leave_when_balance_is_insufficient(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::MANAGER->value,
            'is_active' => true,
        ]);
        $member = $this->createMember();
        $year = (int) now('UTC')->year;

        $quota = LeaveQuota::create([
            'user_id' => $member->id,
            'year' => $year,
            'annual_total' => 14,
            'annual_used' => 0,
            'sick_total' => 2,
            'sick_used' => 1.5,
        ]);

        $leave = WorkSchedule::create([
            'user_id' => $member->id,
            'type' => 'leave',
            'leave_type' => 'sick',
            'leave_days' => 1,
            'all_day' => true,
            'start_at' => now('UTC')->addDay()->startOfDay(),
            'end_at' => now('UTC')->addDay()->endOfDay(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)->post(route('app.reviews.schedules.approve', ['schedule' => $leave->id]));

        $response->assertRedirect();
        $this->assertDatabaseHas('work_schedules', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);
        $quota->refresh();
        $this->assertSame('1.50', $quota->sick_used);
    }
}
