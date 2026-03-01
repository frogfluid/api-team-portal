<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkDailyLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return full dashboard data matching Swift DashboardData struct:
     * { welcome: WelcomeData, stats: DashboardStats, recent_tasks: [TaskData], recent_notifications: [NotificationData] }
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        // Stats matching DashboardStats: active_tasks, completed_today, unread_messages, total_members
        $activeTasks = Task::mine($userId)->whereNotIn('status', ['done'])->count();
        $completedToday = Task::mine($userId)->where('status', 'done')
            ->whereDate('updated_at', Carbon::today())->count();
        $unreadMessages = Message::where('channel_id', '!=', null)
            ->where('user_id', '!=', $userId)
            ->whereDate('created_at', '>=', Carbon::today()->subDays(7))
            ->count(); // approximate unread
        $totalMembers = User::where('is_active', true)->count();

        // Recent tasks (last 5)
        $recentTasks = Task::mine($userId)
            ->with(['creator', 'participants.user'])
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($t) => $this->transformTask($t));

        // Recent notifications (last 5) — uses Laravel's database notification system
        $recentNotifications = $user->notifications()
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn ($n) => [
                'id' => (int) str_replace('-', '', substr($n->id, 0, 8)),
                'user_id' => (int) $n->notifiable_id,
                'type' => data_get($n->data, 'type', 'system'),
                'title' => data_get($n->data, 'title', ''),
                'message' => data_get($n->data, 'message', ''),
                'related_id' => data_get($n->data, 'related_id'),
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        // Motivational quotes
        $quotes = [
            "「一歩一歩、確実に」 — Every step counts.",
            "「今日の努力が、明日の成果になる」",
            "Focus on progress, not perfection.",
            "Team work makes the dream work.",
        ];

        return response()->json([
            'welcome' => [
                'message' => 'Welcome back, ' . $user->name . '!',
                'quote' => $quotes[array_rand($quotes)],
            ],
            'stats' => [
                'active_tasks' => $activeTasks,
                'completed_today' => $completedToday,
                'unread_messages' => $unreadMessages,
                'total_members' => $totalMembers,
            ],
            'recent_tasks' => $recentTasks->values(),
            'recent_notifications' => $recentNotifications->values(),
        ]);
    }

    public function teamOutput(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        $startDate = Carbon::today()->subDays($days - 1);

        $users = User::where('is_active', true)->get(['id', 'name']);

        $labels = [];
        $tasksCompleted = [];
        $logsSubmitted = [];

        foreach ($users as $user) {
            $labels[] = $user->name;

            $completed = Task::where('owner_id', $user->id)
                ->where('status', 'done')
                ->whereDate('updated_at', '>=', $startDate)
                ->count();

            $logs = WorkDailyLog::where('user_id', $user->id)
                ->whereDate('work_date', '>=', $startDate)
                ->where('status', 'submitted')
                ->count();

            $tasksCompleted[] = $completed;
            $logsSubmitted[] = $logs;
        }

        return response()->json([
            'labels' => $labels,
            'tasks_completed' => $tasksCompleted,
            'logs_submitted' => $logsSubmitted,
            'messages_count' => null,
        ]);
    }

    /**
     * Transform task to match Swift TaskData struct.
     */
    private function transformTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'location' => $task->location,
            'status' => $task->status,
            'priority' => $task->priority,
            'progress' => $task->progress ?? 0,
            'assignee_id' => $task->owner_id,
            'assignee_name' => $task->owner?->name,
            'creator_id' => $task->created_by,
            'creator_name' => $task->creator?->name,
            'due_date' => $task->due_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'tags' => $task->tags ?? [],
            'estimated_hours' => $task->estimated_hours,
            'actual_hours' => $task->actual_hours,
            'participants' => $task->participants?->map(fn ($p) => [
                'id' => $p->id,
                'task_id' => $p->task_id,
                'user_id' => $p->user_id,
                'user_name' => $p->user?->name,
                'role' => $p->role ?? 'collaborator',
                'completed_at' => $p->completed_at?->toIso8601String(),
            ])->values(),
            'owner_histories' => null,
        ];
    }
}
