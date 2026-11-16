<?php

use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // API pura: nunca redirigir a una ruta "login" web (no existe); siempre
        // responder 401 en JSON a través del manejador de excepciones de abajo.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todas las excepciones bajo /api/* se responden en JSON de forma consistente.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'Los datos proporcionados no son válidos.',
                    422,
                    $e->errors()
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('No autenticado. Debes enviar un token JWT válido.', 401);
            }
        });

        $exceptions->render(function (JWTException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Token JWT inválido, expirado o ausente.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'No tienes permiso para realizar esta acción.', 403);
            }
        });

        // Nota: Laravel normaliza internamente ModelNotFoundException (incluyendo
        // fallos de route-model-binding) a NotFoundHttpException antes de que
        // lleguen los renderers, por eso el mensaje amigable se resuelve aquí
        // inspeccionando la excepción previa envuelta.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $names = [
                    Product::class => 'Producto',
                    Order::class => 'Orden',
                    Payment::class => 'Pago',
                    User::class => 'Usuario',
                ];

                $model = $names[$previous->getModel()] ?? class_basename($previous->getModel());

                return ApiResponse::error("{$model} no encontrado.", 404);
            }

            return ApiResponse::error('El recurso o la ruta solicitada no existe.', 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'Error al procesar la solicitud.', $e->getStatusCode());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $status = 500;
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'Ocurrió un error inesperado en el servidor.';

                return ApiResponse::error($message, $status);
            }
        });
    })->create();
