<?php

use App\Http\Middleware\ForceJsonResponseMiddleware;
use App\Http\Middleware\IsAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(HandleCors::class);
        $middleware->api(prepend: [ForceJsonResponseMiddleware::class]);

        $middleware->alias([
            'is.admin' => IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (NotFoundHttpException $e, $request) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Ruta no encontrada.',
            ], 404);
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e, $request) {
            return response()->json([
                'status'  => 'error',
                'code'    => 405,
                'message' => 'Método HTTP no permitido para esta ruta.',
            ], 405);
        });

        $exceptions->renderable(function (AuthenticationException $e, $request) {
            return response()->json([
                'status' => 'error',
                'code'   => 401,
                'message' => 'No autenticado. Por favor, inicia sesión.',
            ], 401);
        });
    })->create();
