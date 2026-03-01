<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\DailyLogController;
use App\Http\Controllers\Api\WeeklyReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\LeaveQuotaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AttachmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Architecture: Web ← Database → API → iOS App
| Web uses session auth (Fortify), iOS App uses Sanctum token auth.
| All API routes are prefixed with /api automatically.
|
*/

// ─── Public (no auth) ──────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login']);

// ─── Protected (auth:sanctum) ──────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ────────────────────────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/user/device-token', [AuthController::class, 'storeDeviceToken']);

    // ── Dashboard ───────────────────────────────────────────────────
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/team-output', [DashboardController::class, 'teamOutput']);

    // ── Tasks ───────────────────────────────────────────────────────
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::get('/tasks/{task}/messages', [TaskController::class, 'messages']);
    Route::post('/tasks/{task}/messages', [TaskController::class, 'storeMessage']);
    Route::post('/tasks/{task}/transfer-owner', [TaskController::class, 'transferOwner']);

    // ── Messages (Chat) ─────────────────────────────────────────────
    Route::get('/messages', [ChatController::class, 'getAppMessages']);
    Route::post('/messages', [ChatController::class, 'storeAppMessage']);
    Route::post('/messages/{message}/read', [ChatController::class, 'markAppMessageRead']);
    Route::post('/messages/read-all', [ChatController::class, 'markAllAppMessagesRead']);
    Route::post('/messages/{message}/revoke', [ChatController::class, 'revokeMessage']);

    // ── Channels ────────────────────────────────────────────────────
    Route::get('/channels', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        $query = \App\Models\Channel::where('type', 'public');
        if (!$user->isExecutive()) {
            $query->where('name', '!=', 'Executive Board');
        }
        $channels = $query->orderBy('name')->get();

        // Also include user's private channels (DMs)
        $privateChannels = $user->channels()->where('type', 'private')->get();

        $all = $channels->merge($privateChannels)->unique('id')->values();

        return response()->json($all->map(function ($c) use ($user) {
            $lastRead = $c->users()->where('users.id', $user->id)->first()?->pivot?->last_read_at;
            return [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'type' => $c->type,
                'unread_count' => $lastRead
                    ? $c->messages()->where('created_at', '>', $lastRead)->count()
                    : $c->messages()->count(),
            ];
        }));
    });
    Route::get('/channels/{channel}/messages', [ChatController::class, 'getMessages']);

    // ── Notifications ───────────────────────────────────────────────
    Route::get('/notifications', [ApiNotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [ApiNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/broadcast', function (\Illuminate\Http\Request $request) {
        // API version: just return success
        return response()->json(['message' => 'Broadcast sent.']);
    });

    // ── Daily Logs ──────────────────────────────────────────────────
    Route::get('/daily-logs', [DailyLogController::class, 'index']);
    Route::post('/daily-logs', [DailyLogController::class, 'store']);
    Route::get('/daily-logs/{id}', [DailyLogController::class, 'show']);

    // ── Weekly Reports ──────────────────────────────────────────────
    Route::get('/weekly-reports', [WeeklyReportController::class, 'index']);
    Route::post('/weekly-reports', [WeeklyReportController::class, 'store']);
    Route::get('/weekly-reports/{date}', [WeeklyReportController::class, 'show']);

    // ── Leave Requests ──────────────────────────────────────────────
    Route::get('/leaves', [LeaveController::class, 'index']);
    Route::post('/leaves', [LeaveController::class, 'store']);
    Route::put('/leaves/{leave}', [LeaveController::class, 'update']);
    Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);
    Route::get('/leaves/quota', [LeaveController::class, 'quota']);
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject']);

    // ── Work Schedules ──────────────────────────────────────────────
    Route::get('/work-schedules', [ScheduleController::class, 'index']);
    Route::post('/work-schedules', [ScheduleController::class, 'store']);
    Route::get('/work-schedules/{workSchedule}', [ScheduleController::class, 'show']);
    Route::put('/work-schedules/{workSchedule}', [ScheduleController::class, 'update']);
    Route::delete('/work-schedules/{workSchedule}', [ScheduleController::class, 'destroy']);
    Route::get('/work-schedules/{workSchedule}/comments', [ScheduleController::class, 'comments']);
    Route::post('/work-schedules/{workSchedule}/comments', [ScheduleController::class, 'storeComment']);

    // ── User Profile ────────────────────────────────────────────────
    Route::get('/user/profile', [ProfileController::class, 'show']);
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::post('/user/avatar', [ProfileController::class, 'uploadAvatar']);

    // ── Team Members ────────────────────────────────────────────────
    Route::get('/team-members', [TeamController::class, 'index']);

    // ── Settings ────────────────────────────────────────────────────
    // Swift sends POST for both GET and UPDATE via .notificationPreferences / .workspacePreferences
    Route::match(['get', 'post'], '/settings/notifications', [SettingsController::class, 'handleNotifications']);
    Route::match(['get', 'post'], '/settings/preferences', [SettingsController::class, 'handlePreferences']);

    // ── Search ──────────────────────────────────────────────────────
    Route::get('/search', [SearchController::class, 'search']);

    // ── Attachments ─────────────────────────────────────────────────
    Route::post('/attachments/chunk', [AttachmentController::class, 'chunkUpload']);

    // ── Payroll & Payment Methods ───────────────────────────────────
    Route::get('/payroll', [\App\Http\Controllers\Api\PayrollController::class, 'index']);
    Route::get('/payment-methods', [\App\Http\Controllers\Api\PayrollController::class, 'paymentMethods']);
    Route::post('/payment-methods', [\App\Http\Controllers\Api\PayrollController::class, 'storePaymentMethod']);
    Route::put('/payment-methods/{paymentMethod}', [\App\Http\Controllers\Api\PayrollController::class, 'updatePaymentMethod']);
    Route::delete('/payment-methods/{paymentMethod}', [\App\Http\Controllers\Api\PayrollController::class, 'destroyPaymentMethod']);

    // ── Admin ───────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        // User Management
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::put('/users/{user}', [UserManagementController::class, 'update']);

        // Reviews
        Route::get('/reviews', [ReviewController::class, 'index']);
        // Schedule approve/reject (Swift: /admin/reviews/{id}/approve)
        Route::post('/reviews/{workSchedule}/approve', [ReviewController::class, 'approveSchedule']);
        Route::post('/reviews/{workSchedule}/reject', [ReviewController::class, 'rejectSchedule']);
        // Daily approve/reject/comment
        Route::post('/reviews/daily/{dailyLog}/approve', [ReviewController::class, 'approveDaily']);
        Route::post('/reviews/daily/{dailyLog}/reject', [ReviewController::class, 'rejectDaily']);
        Route::post('/reviews/daily/{dailyLog}/comment', [ReviewController::class, 'commentDaily']);
        // Weekly approve/reject/comment
        Route::post('/reviews/weekly/{weeklyReport}/approve', [ReviewController::class, 'approveWeekly']);
        Route::post('/reviews/weekly/{weeklyReport}/reject', [ReviewController::class, 'rejectWeekly']);
        Route::post('/reviews/weekly/{weeklyReport}/comment', [ReviewController::class, 'commentWeekly']);

        // Leave Quotas
        Route::get('/leave-quotas', [LeaveQuotaController::class, 'index']);
        Route::put('/leave-quotas/{user}', [LeaveQuotaController::class, 'update']);

        // Payroll Management
        Route::get('/payroll', [\App\Http\Controllers\Api\PayrollController::class, 'adminIndex']);
        Route::get('/payroll/summary', [\App\Http\Controllers\Api\PayrollController::class, 'adminSummary']);
        Route::post('/payroll/publish', [\App\Http\Controllers\Api\PayrollController::class, 'adminPublish']);
        Route::post('/payroll/copy-previous', [\App\Http\Controllers\Api\PayrollController::class, 'adminCopyPrevious']);
        Route::post('/payroll/{user}', [\App\Http\Controllers\Api\PayrollController::class, 'adminStore']);
    });
});
