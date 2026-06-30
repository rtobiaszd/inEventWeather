<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                $firstError = collect($e->errors())->flatten()->first();
                return response()->json(['success' => false, 'message' => $firstError], 422);
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['success' => false, 'message' => 'Não autenticado'], 401);
                }

                if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                    return response()->json(['success' => false, 'message' => 'Muitas requisições. Aguarde um momento.'], 429);
                }

                if ($e instanceof \RuntimeException || $e instanceof \Illuminate\Validation\ValidationException) {
                    $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : ($e instanceof \Illuminate\Validation\ValidationException ? 422 : 400);
                    return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
                }

                $message = app()->isProduction()
                    ? 'Erro interno do servidor'
                    : $e->getMessage();

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json(['success' => false, 'message' => $message], $status);
            }
        });
    })->create();
