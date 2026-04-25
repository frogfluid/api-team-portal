<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\User;
use App\Models\WorkDailyLog;
use App\Notifications\DailyLogSubmitted;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $request->query('scope', 'self');
        $status = $request->query('status');
        $limit = (int) $request->query('limit', 30);
        $limit = max(1, min($limit, 200));

        $query = WorkDailyLog::query()
            ->with('user:id,name')
            ->orderByDesc('work_date');

        $canReview = $user->role instanceof UserRole && $user->role->canReview();

        if ($scope === 'team' && $canReview) {
            if ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->query('user_id'));
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        // Incremental sync support
        if (! $request->boolean('force_full') && $request->filled('updated_since')) {
            try {
                $since = \Carbon\Carbon::parse((string) $request->updated_since);
                $query->where('updated_at', '>', $since);
            } catch (\Exception $e) {
                // Malformed timestamp — fall through to full list.
            }
        }

        $logs = $query->limit($limit)->get();

        return response()->json($logs->map(fn($l) => $this->transformDailyLog($l))->values());
    }

    public function show(Request $request, $id = null): JsonResponse
    {
        $user = $request->user();

        if ($id) {
            $log = WorkDailyLog::where('user_id', $user->id)->findOrFail($id);
        } else {
            $dateParam = $request->query('date');
            $today = $dateParam ? Carbon::parse($dateParam)->startOfDay() : Carbon::today();
            $log = $this->findOrCreateLog($user->id, $today);
        }

        $log->load(['attachments', 'user:id,name']);

        return response()->json($this->transformDailyLog($log));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $dateParam = $request->input('date');
        $targetDate = $dateParam ? Carbon::parse($dateParam)->startOfDay() : Carbon::today();

        $data = $request->validate([
            'note' => ['nullable', 'string'],
            'action' => ['nullable', 'in:save,submit'],
        ]);

        $action = $request->input('action', 'save');

        $log = $this->findOrCreateLog($user->id, $targetDate);

        $log->fill([
            'note' => $data['note'] ?? null,
        ]);

        if ($log->started_at && $log->ended_at) {
            $minutes = $log->started_at->diffInMinutes($log->ended_at);
            $minutes = max(0, $minutes - (int) $log->break_minutes);
            $log->worked_minutes = $minutes;
        } else {
            $log->worked_minutes = null;
        }

        if ($action === 'submit') {
            $log->status = 'submitted';
            $log->submitted_at = now();
        } else {
            $log->status = 'draft';
            $log->submitted_at = null;
        }

        $log->save();

        if ($action === 'submit') {
            $reviewers = User::query()
                ->whereIn('role', [UserRole::ADMIN->value, UserRole::MANAGER->value])
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($reviewers as $reviewer) {
                $reviewer->notify(new DailyLogSubmitted($log, $user));
            }
        }

        $log->load('user:id,name');

        return response()->json($this->transformDailyLog($log));
    }

    private function transformDailyLog(WorkDailyLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'date' => $log->work_date?->toDateString(),
            'started_at' => $log->started_at?->toIso8601String(),
            'ended_at' => $log->ended_at?->toIso8601String(),
            'worked_minutes' => $log->worked_minutes,
            'work_summary' => $log->note ?? '',
            'completed_tasks' => '',
            'tomorrow_plan' => '',
            'issues' => null,
            'user_name' => $log->user?->name,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function findOrCreateLog(int $userId, Carbon $date): WorkDailyLog
    {
        $dateKey = $date->toDateString();
        $existing = WorkDailyLog::where('user_id', $userId)->whereDate('work_date', $dateKey)->first();
        if ($existing)
            return $existing;

        try {
            return WorkDailyLog::create([
                'user_id' => $userId,
                'work_date' => $dateKey,
                'break_minutes' => 0,
                'status' => 'draft',
            ]);
        } catch (QueryException $e) {
            return WorkDailyLog::where('user_id', $userId)->whereDate('work_date', $dateKey)->firstOrFail();
        }
    }
}
