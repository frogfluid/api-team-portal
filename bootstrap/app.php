<?php

use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\RequestId::class,
            \App\Http\Middleware\WrapApiResponse::class,
        ]);
        $middleware->alias([
            'active_user' => EnsureActiveUser::class,
        ]);
    })
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $map = [
                \Illuminate\Validation\ValidationException::class    => ['VALIDATION_ERROR', 422],
                \Illuminate\Auth\AuthenticationException::class      => ['UNAUTHORIZED', 401],
                \Illuminate\Auth\Access\AuthorizationException::class => ['FORBIDDEN', 403],
                \Illuminate\Database\Eloquent\ModelNotFoundException::class => ['NOT_FOUND', 404],
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class => ['NOT_FOUND', 404],
                \App\Exceptions\PayrollLockedException::class        => ['PAYROLL_LOCKED', 409],
            ];

            foreach ($map as $class => [$code, $status]) {
                if ($e instanceof $class) {
                    if ($e instanceof \Illuminate\Validation\ValidationException) {
                        return response()->json([
                            'error_code' => $code,
                            'message' => $e->getMessage(),
                            'errors' => $e->errors(),
                        ], $status);
                    }
                    return response()->json([
                        'error_code' => $code,
                        'message' => $e->getMessage() ?: $code,
                    ], $status);
                }
            }

            return null; // Fall through to default handler.
        });
    })->create();
