<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFilterTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(UserRole $role = UserRole::MEMBER): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'is_active' => true,
        ]);
    }

    // ─── Filter by Status ───

    public function test_filter_by_status_pending(): void
    {
        $user = $this->createUser();
        Task::factory()->create(['owner_id' => $user->id, 'status' => 'pending']);
        Task::factory()->create(['owner_id' => $user->id, 'status' => 'completed']);

        $response = $this->actingAs($user)
            ->get(route('app.tasks.index', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertSee('pending');
    }

    // ─── Filter by User ───

    public function test_filter_by_user_ids(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        Task::factory()->create(['owner_id' => $user1->id, 'status' => 'pending', 'title' => 'User1 Task']);
        Task::factory()->create(['owner_id' => $user2->id, 'status' => 'pending', 'title' => 'User2 Task']);

        $response = $this->actingAs($user1)
            ->get(route('app.tasks.index', ['tab' => 'all', 'user_ids' => [$user1->id]]));

        $response->assertStatus(200);
        $response->assertSee('User1 Task');
        $response->assertDontSee('User2 Task');
    }

    public function test_filter_by_multiple_users_requires_intersection(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        // Task only for User 1
        Task::factory()->create(['owner_id' => $user1->id, 'title' => 'Solo Task']);

        // Collaborative Task for User 1 and User 2
        $collabTask = Task::factory()->create(['owner_id' => $user1->id, 'title' => 'Collab Task']);
        $collabTask->participants()->attach($user2->id);

        $response = $this->actingAs($user1)
            ->get(route('app.tasks.index', ['tab' => 'all', 'user_ids' => [$user1->id, $user2->id]]));

        $response->assertStatus(200);
        $response->assertSee('Collab Task');
        $response->assertDontSee('Solo Task'); // Intersection required
    }

    public function test_filter_by_single_user_no_tasks_empty_state_message(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $response = $this->actingAs($user1)
            ->get(route('app.tasks.index', ['tab' => 'all', 'user_ids' => [$user2->id]]));

        $response->assertStatus(200);
        $response->assertSee('The selected user has no tasks now.');
    }

    public function test_filter_by_multiple_users_no_collab_tasks_empty_state_message(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $user3 = $this->createUser();

        // User 2 and User 3 each have solo tasks, but no collaborative tasks
        Task::factory()->create(['owner_id' => $user2->id, 'title' => 'User2 Solo Task']);
        Task::factory()->create(['owner_id' => $user3->id, 'title' => 'User3 Solo Task']);

        $response = $this->actingAs($user1)
            ->get(route('app.tasks.index', ['tab' => 'all', 'user_ids' => [$user2->id, $user3->id]]));

        $response->assertStatus(200);
        $response->assertSee('The selected users have no tasks now.');
    }

    // ─── Filter by Priority ───

    public function test_filter_by_priority(): void
    {
        $user = $this->createUser();
        Task::factory()->create(['owner_id' => $user->id, 'priority' => 'high']);
        Task::factory()->create(['owner_id' => $user->id, 'priority' => 'low']);

        $response = $this->actingAs($user)
            ->get(route('app.tasks.index', ['priority' => 'high']));

        $response->assertStatus(200);
    }

    // ─── Filter by Date Range ───

    public function test_filter_by_due_date_range(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->get(route('app.tasks.index', [
                'due_from' => now()->format('Y-m-d'),
                'due_to' => now()->addWeek()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
    }

    // ─── Filter by Keyword ───

    public function test_filter_by_keyword(): void
    {
        $user = $this->createUser();
        Task::factory()->create([
            'owner_id' => $user->id,
            'title' => 'Unique searchable title xyz',
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.tasks.index', ['q' => 'xyz']));

        $response->assertStatus(200);
    }

    // ─── Empty Filter Returns All ───

    public function test_empty_filter_returns_results(): void
    {
        $user = $this->createUser();
        Task::factory()->count(3)->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('app.tasks.index'));

        $response->assertStatus(200);
    }

    // ─── Apply Button (no save/clear params) ───

    public function test_apply_does_not_save_filter(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->get(route('app.tasks.index', ['status' => 'pending']));

        $response->assertStatus(200);
        // Verify no save/usesave/clear buttons in the view
        $response->assertDontSee('Save Filter');
        $response->assertDontSee('Use Saved');
    }
}
