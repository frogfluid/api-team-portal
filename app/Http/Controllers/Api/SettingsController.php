<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function getNotifications(Request $request): JsonResponse
    {
        $prefs = $this->getPreferences($request->user());

        return response()->json($prefs['notifications']);
    }

    public function handleNotifications(Request $request): JsonResponse
    {
        $allPreferences = $this->getPreferences($request->user());

        $input = $request->json()->all();
        if (!empty($input)) {
            $request->validate([
                'email_daily' => ['nullable', 'boolean'],
                'push_messages' => ['nullable', 'boolean'],
                'push_mentions' => ['nullable', 'boolean'],
                'push_task_updates' => ['nullable', 'boolean'],
            ]);

            $notif = $allPreferences['notifications'];
            if ($request->has('email_daily')) $notif['email_daily'] = $request->boolean('email_daily');
            if ($request->has('push_messages')) $notif['push_messages'] = $request->boolean('push_messages');
            if ($request->has('push_mentions')) $notif['push_mentions'] = $request->boolean('push_mentions');
            if ($request->has('push_task_updates')) $notif['push_task_updates'] = $request->boolean('push_task_updates');

            $allPreferences['notifications'] = $notif;
            $request->user()->update(['preferences' => $allPreferences]);

            return response()->json($allPreferences['notifications']);
        }

        return response()->json($allPreferences['notifications']);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'email_daily' => ['nullable', 'boolean'],
            'push_messages' => ['nullable', 'boolean'],
            'push_mentions' => ['nullable', 'boolean'],
        ]);

        $allPreferences = $this->getPreferences($request->user());
        $allPreferences['notifications'] = [
            'email_daily' => $request->boolean('email_daily'),
            'push_messages' => $request->boolean('push_messages'),
            'push_mentions' => $request->boolean('push_mentions'),
        ];

        $request->user()->update(['preferences' => $allPreferences]);

        return response()->json(['message' => 'Notification preferences updated.', 'notifications' => $allPreferences['notifications']]);
    }

    public function getPreferencesEndpoint(Request $request): JsonResponse
    {
        $prefs = $this->getPreferences($request->user());

        return response()->json($prefs['workspace']);
    }

    public function handlePreferences(Request $request): JsonResponse
    {
        $allPreferences = $this->getPreferences($request->user());

        $input = $request->json()->all();
        if (!empty($input)) {
            $data = $request->validate([
                'default_task_tab' => ['sometimes', 'string', 'in:my,all'],
                'default_calendar_view' => ['sometimes', 'string', 'in:month,week,day'],
                'timezone' => ['sometimes', 'string', 'max:64'],
                'compact_mode' => ['nullable', 'boolean'],
            ]);

            if (isset($data['default_task_tab'])) $allPreferences['workspace']['default_task_tab'] = $data['default_task_tab'];
            if (isset($data['default_calendar_view'])) $allPreferences['workspace']['default_calendar_view'] = $data['default_calendar_view'];
            if (isset($data['timezone'])) $allPreferences['workspace']['timezone'] = $data['timezone'];
            if ($request->has('compact_mode')) $allPreferences['workspace']['compact_mode'] = $request->boolean('compact_mode');

            $request->user()->update(['preferences' => $allPreferences]);

            return response()->json($allPreferences['workspace']);
        }

        return response()->json($allPreferences['workspace']);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_task_tab' => ['sometimes', 'string', 'in:my,all'],
            'default_calendar_view' => ['sometimes', 'string', 'in:month,week,day'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'compact_mode' => ['nullable', 'boolean'],
        ]);

        $allPreferences = $this->getPreferences($request->user());

        if (isset($data['default_task_tab'])) $allPreferences['workspace']['default_task_tab'] = $data['default_task_tab'];
        if (isset($data['default_calendar_view'])) $allPreferences['workspace']['default_calendar_view'] = $data['default_calendar_view'];
        if (isset($data['timezone'])) $allPreferences['workspace']['timezone'] = $data['timezone'];
        if ($request->has('compact_mode')) $allPreferences['workspace']['compact_mode'] = $request->boolean('compact_mode');

        $request->user()->update(['preferences' => $allPreferences]);

        return response()->json(['message' => 'Preferences updated.', 'workspace' => $allPreferences['workspace']]);
    }

    private function getPreferences($user): array
    {
        $defaults = [
            'notifications' => [
                'email_daily' => true,
                'push_messages' => true,
                'push_mentions' => true,
                'push_task_updates' => true,
            ],
            'workspace' => [
                'default_task_tab' => 'my',
                'default_calendar_view' => 'month',
                'timezone' => 'Asia/Tokyo',
                'compact_mode' => false,
            ],
        ];

        $stored = is_array($user->preferences) ? $user->preferences : [];

        return [
            'notifications' => array_merge($defaults['notifications'], $stored['notifications'] ?? []),
            'workspace' => array_merge($defaults['workspace'], $stored['workspace'] ?? []),
        ];
    }
}
