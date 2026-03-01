<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Handle the global Cmd+K search queries.
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (empty($query)) {
            return response()->json([]);
        }

        $results = [];

        // 1. Search Tasks (by title or ID)
        $tasks = Task::where('title', 'like', "%{$query}%")
            ->orWhere('id', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        foreach ($tasks as $task) {
            $results[] = [
                'type' => 'task',
                'title' => $task->title,
                'subtitle' => "Task #{$task->id} · ".ucfirst($task->status),
                'url' => route('app.tasks.show', $task->id),
                'icon' => 'task',
            ];
        }

        // 2. Search Users (by name or employee ID)
        $users = User::with('employee')
            ->where('name', 'like', "%{$query}%")
            ->where('is_active', true)
            ->limit(3)
            ->get();

        foreach ($users as $user) {
            $url = (int) $user->id === (int) $request->user()->id
                ? route('app.profile.edit')
                : route('app.chat.dm', $user);

            $results[] = [
                'type' => 'user',
                'title' => $user->name,
                'subtitle' => $user->role->label().($user->department ? ' · '.$user->department->label() : ''),
                'url' => $url,
                'icon' => 'user',
                'avatar' => $user->avatar_url,
            ];
        }

        // 3. Static Commands
        $commands = [
            ['title' => 'Create New Task', 'subtitle' => 'Jump to task creation', 'url' => route('app.tasks.create'), 'icon' => 'plus', 'keywords' => ['new', 'create', 'task']],
            ['title' => 'Write Daily Log', 'subtitle' => "Submit today's report", 'url' => route('app.daily.show'), 'icon' => 'document', 'keywords' => ['daily', 'log', 'report']],
            ['title' => 'View Schedule', 'subtitle' => 'Open calendar view', 'url' => route('app.schedules.calendar'), 'icon' => 'calendar', 'keywords' => ['schedule', 'calendar']],
            ['title' => 'Request Leave', 'subtitle' => 'Open leave request workspace', 'url' => route('app.leaves.index'), 'icon' => 'calendar', 'keywords' => ['leave', 'vacation', 'pto']],
        ];

        foreach ($commands as $command) {
            foreach ($command['keywords'] as $keyword) {
                if (stripos($keyword, $query) !== false || stripos($command['title'], $query) !== false) {
                    $results[] = [
                        'type' => 'command',
                        'title' => $command['title'],
                        'subtitle' => $command['subtitle'],
                        'url' => $command['url'],
                        'icon' => $command['icon'],
                    ];
                    break;
                }
            }
        }

        return response()->json($results);
    }
}
