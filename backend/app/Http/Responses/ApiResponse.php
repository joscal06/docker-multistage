<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Helper para construir respuestas JSON consistentes en toda la API.
 */
class ApiResponse
{
    /**
     * Respuesta de éxito con datos.
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], fn ($value) => ! is_null($value)) + ['success' => true], $status);
    }

    /**
     * Respuesta de error con un mensaje descriptivo.
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
