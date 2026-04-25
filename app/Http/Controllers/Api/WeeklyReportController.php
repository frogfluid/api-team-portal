<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportSubmitted;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WeeklyReport::query()
            ->where('user_id', $user->id)
            ->with('user:id,name')
            ->orderByDesc('week_start_date')
            ->limit(20);

        if (! $request->boolean('force_full') && $request->filled('updated_since')) {
            try {
                $since = \Carbon\Carbon::parse((string) $request->updated_since);
                $query->where('updated_at', '>', $since);
            } catch (\Exception $e) {
                // Malformed timestamp — fall through to full list.
            }
        }

        $reports = $query->get();

        return response()->json($reports->map(fn ($r) => $this->transformWeeklyReport($r))->values());
    }

    public function show(Request $request, $date): JsonResponse
    {
        $user = $request->user();
        $weekStart = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $report = $this->findOrCreateReport($user->id, $weekStart);
        $report->load(['attachments', 'user:id,name']);

        return response()->json($this->transformWeeklyReport($report));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $dateParam = $request->input('date');
        $weekStart = $dateParam ? Carbon::parse($dateParam)->startOfWeek(Carbon::MONDAY) : Carbon::today()->startOfWeek(Carbon::MONDAY);

        $data = $request->validate([
            'summary' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'issues' => ['nullable', 'string'],
            'next_week_plan' => ['nullable', 'string'],
            'ideas' => ['nullable', 'string'],
            'support_needed' => ['nullable', 'string'],
            'action' => ['nullable', 'in:save,submit'],
        ]);

        $action = $request->input('action', 'save');

        $report = $this->findOrCreateReport($user->id, $weekStart);

        $report->fill([
            'summary' => $data['summary'] ?? null,
            'achievements' => $data['achievements'] ?? null,
            'issues' => $data['issues'] ?? null,
            'next_week_plan' => $data['next_week_plan'] ?? null,
            'ideas' => $data['ideas'] ?? null,
            'support_needed' => $data['support_needed'] ?? null,
        ]);

        if ($action === 'submit') {
            $report->status = 'submitted';
            $report->submitted_at = now();
        } else {
            $report->status = 'draft';
            $report->submitted_at = null;
        }

        $report->save();

        if ($action === 'submit') {
            $reviewers = User::query()
                ->whereIn('role', [UserRole::ADMIN->value, UserRole::MANAGER->value])
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($reviewers as $reviewer) {
                $reviewer->notify(new WeeklyReportSubmitted($report, $user));
            }
        }

        $report->load('user:id,name');

        return response()->json($this->transformWeeklyReport($report));
    }

    private function transformWeeklyReport(WeeklyReport $report): array
    {
        return [
            'id' => $report->id,
            'user_id' => $report->user_id,
            'week_start_date' => $report->week_start_date?->toDateString(),
            'summary' => $report->summary ?? '',
            'achievements' => $report->achievements ?? '',
            'issues' => $report->issues,
            'next_week_plan' => $report->next_week_plan,
            'ideas' => $report->ideas,
            'support_needed' => $report->support_needed,
            'status' => $report->status,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'user_name' => $report->user?->name,
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    private function findOrCreateReport(int $userId, Carbon $weekStart): WeeklyReport
    {
        $dateKey = $weekStart->toDateString();
        $existing = WeeklyReport::where('user_id', $userId)->whereDate('week_start_date', $dateKey)->first();
        if ($existing) return $existing;

        try {
            return WeeklyReport::create([
                'user_id' => $userId,
                'week_start_date' => $dateKey,
                'status' => 'draft',
            ]);
        } catch (QueryException $e) {
            return WeeklyReport::where('user_id', $userId)->whereDate('week_start_date', $dateKey)->firstOrFail();
        }
    }
}
