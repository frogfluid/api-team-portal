<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wraps all API JSON responses in the standard APIResponse format
 * that the iOS app expects: {"success": bool, "message": ?string, "data": mixed}
 *
 * Exceptions:
 * - Login/refresh responses containing a 'token' key are passed through unchanged.
 * - Responses already containing a 'success' key are passed through unchanged.
 */
class WrapApiResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only process JSON responses
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();

        // Skip if already wrapped (has 'success' key as associative array)
        if (is_array($data) && ! array_is_list($data) && array_key_exists('success', $data)) {
            return $response;
        }

        // Skip token responses (login / refresh) — iOS decodes these directly
        if (is_array($data) && ! array_is_list($data) && isset($data['token'])) {
            return $response;
        }

        // ── Error responses (4xx, 5xx) ──────────────────────────────
        if ($statusCode >= 400) {
            $message = 'Error';
            if (is_array($data) && ! array_is_list($data)) {
                $message = $data['message'] ?? 'Error';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'data'    => null,
            ], $statusCode);
        }

        // ── Success responses ───────────────────────────────────────
        $message = null;
        $wrappedData = $data;

        // Extract 'message' from associative arrays and move it to the wrapper level
        if (is_array($data) && ! array_is_list($data) && isset($data['message'])) {
            $message = $data['message'];
            unset($wrappedData['message']);

            // If only message was present, data becomes null
            if (empty($wrappedData)) {
                $wrappedData = null;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $wrappedData,
        ]);
    }
}
