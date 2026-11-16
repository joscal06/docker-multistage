<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'E-commerce API',
    description: 'API RESTful para la gestión de un e-commerce: clientes, catálogo de productos, '
        .'órdenes de compra y procesamiento de pagos mediante Stripe.',
    contact: new OA\Contact(name: 'Soporte E-commerce API', email: 'soporte@ecommerce.test')
)]
#[OA\Server(url: 'http://localhost/ecommerce/public', description: 'Servidor local (XAMPP)')]
#[OA\Server(url: 'http://127.0.0.1:8000', description: 'Servidor local (php artisan serve)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token JWT obtenido en /api/auth/login o /api/auth/register. Enviar como: Authorization: Bearer {token}'
)]
#[OA\Tag(name: 'Auth', description: 'Registro, autenticación y sesión del cliente')]
#[OA\Tag(name: 'Products', description: 'Catálogo de productos')]
#[OA\Tag(name: 'Orders', description: 'Órdenes de compra')]
#[OA\Tag(name: 'Payments', description: 'Procesamiento de pagos con Stripe')]
abstract class Controller
{
    //
}
