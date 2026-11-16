<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Esquemas de respuesta compartidos entre los distintos endpoints de la API.
 * Esta clase no contiene lógica: únicamente agrupa las definiciones de
 * OpenAPI reutilizadas mediante $ref en toda la documentación Swagger.
 */
#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'Respuesta de error',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Mensaje descriptivo del error.'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    title: 'Error de validación',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Los datos proporcionados no son válidos.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['El correo electrónico ya ha sido registrado.']]
        ),
    ]
)]
class SharedSchemas {}
