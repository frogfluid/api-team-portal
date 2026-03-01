<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LeaveQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeaveQuotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(UserRole $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role->value,
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_can_view_leave_quota_management_section(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $manager = $this->createUser(UserRole::MANAGER, ['name' => 'Manager A']);
        $member = $this->createUser(UserRole::MEMBER, ['name' => 'Member A']);
        $intern = $this->createUser(UserRole::INTERN, ['name' => 'Intern A']);

        $response = $this->actingAs($admin)->get(route('app.reviews.index'));

        $response->assertOk();
        $response->assertSee('Leave Quota Management');
        $response->assertSee($manager->name);
        $response->assertSee($member->name);
        $response->assertDontSee($intern->name);
    }

    public function test_admin_can_update_member_leave_quota(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $member = $this->createUser(UserRole::MEMBER);

        $response = $this->actingAs($admin)->post(route('app.reviews.leave-quotas.update', $member), [
            'year' => now()->year,
            'annual_total' => 15,
            'sick_total' => 8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_quotas', [
            'user_id' => $member->id,
            'year' => now()->year,
            'annual_total' => 15.00,
            'sick_total' => 8.00,
        ]);
    }

    public function test_manager_cannot_update_leave_quota(): void
    {
        $manager = $this->createUser(UserRole::MANAGER);
        $member = $this->createUser(UserRole::MEMBER);

        $response = $this->actingAs($manager)->post(route('app.reviews.leave-quotas.update', $member), [
            'year' => now()->year,
            'annual_total' => 12,
            'sick_total' => 6,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_set_quota_total_below_used_days(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $member = $this->createUser(UserRole::MEMBER);

        LeaveQuota::create([
            'user_id' => $member->id,
            'year' => now()->year,
            'annual_total' => 12,
            'annual_used' => 6,
            'sick_total' => 8,
            'sick_used' => 3,
        ]);

        $response = $this->actingAs($admin)->post(route('app.reviews.leave-quotas.update', $member), [
            'year' => now()->year,
            'annual_total' => 4,
            'sick_total' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('leave_quotas', [
            'user_id' => $member->id,
            'year' => now()->year,
            'annual_total' => 12.00,
            'sick_total' => 8.00,
        ]);
    }
}
