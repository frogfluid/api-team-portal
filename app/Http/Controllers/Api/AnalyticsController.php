<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Task;
use App\Models\Project;
use App\Models\WorkDeliverable;
use App\Models\Objective;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Admin: global team analytics.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $now = now();

        // Team-wide task stats
        $totalTasks = Task::count();
        $completedTasks = Task::where('status', 'completed')->count();
        $inProgressTasks = Task::where('status', 'in_progress')->count();
        $overdueTasks = Task::where('status', '!=', 'completed')
            ->where('due_at', '<', $now)->count();

        // Tasks by status
        $tasksByStatus = Task::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')->pluck('count', 'status');

        // Tasks by priority
        $tasksByPriority = Task::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')->pluck('count', 'priority');

        // Top 5 contributors (by completed tasks this month)
        $topContributors = User::select('users.id', 'users.name', 'users.avatar_path')
            ->leftJoin('tasks', function ($join) use ($now) {
                $join->on('users.id', '=', 'tasks.owner_id')
                    ->where('tasks.status', 'completed')
                    ->whereMonth('tasks.updated_at', $now->month)
                    ->whereYear('tasks.updated_at', $now->year);
            })
            ->selectRaw('COUNT(tasks.id) as completed_count')
            ->groupBy('users.id', 'users.name', 'users.avatar_path')
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->avatar_url ?? null,
                'completed_count' => (int) $u->completed_count,
            ]);

        // Team attendance this month
        $attendanceStats = AttendanceRecord::whereMonth('date', $now->month)
            ->whereYear('date', $now->year)
            ->selectRaw('COUNT(DISTINCT user_id) as active_employees')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_count")
            ->selectRaw('COALESCE(SUM(work_duration_minutes), 0) as total_minutes')
            ->first();

        // Daily activity (last 14 days)
        $dailyActivity = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dailyActivity[] = [
                'date' => $date->format('M d'),
                'completed' => (int) Task::where('status', 'completed')
                    ->whereDate('updated_at', $date->toDateString())->count(),
            ];
        }

        // Active projects
        $activeProjects = Project::where('status', 'active')->count();
        $totalProjects = Project::count();

        return response()->json([
            'data' => [
                'tasks' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'in_progress' => $inProgressTasks,
                    'overdue' => $overdueTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
                    'by_status' => $tasksByStatus,
                    'by_priority' => $tasksByPriority,
                ],
                'projects' => [
                    'total' => $totalProjects,
                    'active' => $activeProjects,
                ],
                'attendance' => [
                    'active_employees' => (int) ($attendanceStats->active_employees ?? 0),
                    'total_records' => (int) ($attendanceStats->total_records ?? 0),
                    'late_count' => (int) ($attendanceStats->late_count ?? 0),
                    'early_leave_count' => (int) ($attendanceStats->early_leave_count ?? 0),
                    'total_hours' => round(((float) ($attendanceStats->total_minutes ?? 0)) / 60, 1),
                ],
                'top_contributors' => $topContributors,
                'daily_activity' => $dailyActivity,
            ],
        ]);
    }
    /**
     * Personal analytics dashboard data.
     */
    public function personal(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        // Task stats
        $tasks = Task::mine($user->id);
        $totalTasks = (clone $tasks)->count();
        $completedTasks = (clone $tasks)->where('status', 'completed')->count();
        $overdueTasks = (clone $tasks)->where('status', '!=', 'completed')
            ->where('due_at', '<', $now)->count();

        // This month attendance
        $attendance = AttendanceRecord::forUser($user->id)
            ->whereMonth('date', $now->month)
            ->whereYear('date', $now->year);
        $attendanceDays = (clone $attendance)->count();
        $totalHours = (clone $attendance)->sum('work_duration_minutes');

        // Deliverables count
        $deliverableCount = WorkDeliverable::where('user_id', $user->id)->count();

        // OKR progress
        $okrObjectives = Objective::where('owner_id', $user->id)->where('status', 'active');
        $avgOkrProgress = (clone $okrObjectives)->avg('progress') ?? 0;

        return response()->json([
            'data' => [
                'tasks' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'overdue' => $overdueTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
                ],
                'attendance' => [
                    'days_this_month' => $attendanceDays,
                    'total_hours' => round($totalHours / 60, 1),
                    'avg_hours_per_day' => $attendanceDays > 0 ? round(($totalHours / $attendanceDays) / 60, 1) : 0,
                ],
                'deliverables' => [
                    'total' => $deliverableCount,
                ],
                'okr' => [
                    'avg_progress' => (int) $avgOkrProgress,
                    'active_objectives' => (clone $okrObjectives)->count(),
                ],
            ],
        ]);
    }

    /**
     * Weekly task trend (last 4 weeks).
     */
    public function taskTrend(Request $request): JsonResponse
    {
        $user = $request->user();
        $weeks = [];

        for ($i = 3; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = now()->subWeeks($i)->endOfWeek();
            $completed = Task::mine($user->id)
                ->where('status', 'completed')
                ->whereBetween('updated_at', [$start, $end])
                ->count();
            $weeks[] = [
                'week_label' => $start->format('M d'),
                'completed' => $completed,
            ];
        }

        return response()->json(['data' => $weeks]);
    }
}
