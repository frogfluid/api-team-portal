<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs?q=...&action=...&page=...
     * Admin only — paginated audit trail.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $keyword = trim((string) $request->query('q', ''));
        $actionFilter = (string) $request->query('action', '');
        $perPage = max(10, min(100, (int) $request->query('per_page', 30)));

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($qq) use ($keyword) {
                    $qq->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$keyword}%"))
                       ->orWhere('auditable_type', 'like', "%{$keyword}%")
                       ->orWhere('action', 'like', "%{$keyword}%");
                });
            })
            ->when($actionFilter !== '', fn ($q) => $q->where('action', $actionFilter))
            ->orderByDesc('created_at');

        $page = $query->paginate($perPage);

        return response()->json([
            'logs' => $page->getCollection()->map(fn ($l) => $this->transform($l)),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    /**
     * GET /api/admin/audit-logs/actions
     * Returns the distinct list of audit actions, for filter chips.
     */
    public function actions(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return response()->json(['actions' => $actions]);
    }

    private function ensureAdmin(Request $request): void
    {
        if (! ($request->user()->role instanceof UserRole) || $request->user()->role !== UserRole::ADMIN) {
            throw new AuthorizationException('Admin only.');
        }
    }

    private function transform(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'action' => $log->action,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'created_at' => optional($log->created_at)->toIso8601String(),
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ] : null,
        ];
    }
}
