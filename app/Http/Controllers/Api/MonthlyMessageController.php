<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyMessageController extends Controller
{
    /**
     * GET /api/feedback
     * Employee: list their monthly messages, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $messages = MonthlyMessage::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'author:id,name,avatar_path',
                'comments:id,monthly_message_id,author_id,body,created_at',
                'comments.author:id,name,avatar_path',
            ])
            ->orderByDesc('target_month')
            ->get()
            ->map(fn ($m) => $this->transform($m));

        return response()->json(['messages' => $messages]);
    }

    /**
     * GET /api/feedback/{monthlyMessage}
     * Employee: view one message in full (with comments).
     */
    public function show(Request $request, MonthlyMessage $monthlyMessage): JsonResponse
    {
        $this->ensureOwn($request, $monthlyMessage);

        $monthlyMessage->load([
            'author:id,name,avatar_path',
            'comments.author:id,name,avatar_path',
        ]);

        return response()->json(['message' => $this->transform($monthlyMessage)]);
    }

    /**
     * POST /api/feedback/{monthlyMessage}/confirm
     * Employee: submit a written response and mark confirmed.
     */
    public function confirm(Request $request, MonthlyMessage $monthlyMessage): JsonResponse
    {
        $this->ensureOwn($request, $monthlyMessage);

        if ($monthlyMessage->confirmed_at !== null) {
            return response()->json([
                'message' => $this->transform($monthlyMessage->fresh()),
                'already_confirmed' => true,
            ]);
        }

        $validated = $request->validate([
            'response' => ['required', 'string', 'min:5', 'max:65535'],
        ]);

        $monthlyMessage->update([
            'confirmed_at' => now(),
            'response' => $validated['response'],
        ]);

        return response()->json([
            'message' => $this->transform($monthlyMessage->fresh(['author:id,name,avatar_path', 'comments.author:id,name,avatar_path'])),
            'already_confirmed' => false,
        ]);
    }

    // MARK: - Helpers

    private function ensureOwn(Request $request, MonthlyMessage $message): void
    {
        if ($message->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function transform(MonthlyMessage $m): array
    {
        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'author_id' => $m->author_id,
            'target_month' => optional($m->target_month)->toDateString(),
            'review' => $m->review,
            'goals' => $m->goals ?? [],
            'confirmed_at' => optional($m->confirmed_at)->toIso8601String(),
            'response' => $m->response,
            'created_at' => optional($m->created_at)->toIso8601String(),
            'updated_at' => optional($m->updated_at)->toIso8601String(),
            'author' => $m->author ? [
                'id' => $m->author->id,
                'name' => $m->author->name,
                'avatar_path' => $m->author->avatar_path,
            ] : null,
            'comments' => $m->comments->map(fn ($c) => [
                'id' => $c->id,
                'monthly_message_id' => $c->monthly_message_id,
                'author_id' => $c->author_id,
                'body' => $c->body,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'author' => $c->author ? [
                    'id' => $c->author->id,
                    'name' => $c->author->name,
                    'avatar_path' => $c->author->avatar_path,
                ] : null,
            ])->values(),
        ];
    }
}
