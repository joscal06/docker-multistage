<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Todas las rutas aquí definidas se registran automáticamente bajo el
| prefijo "/api" y el grupo de middleware "api" (ver bootstrap/app.php).
|
*/

// --- Autenticación --------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// --- Webhook de Stripe (público, verificado por firma) --------------------
Route::post('stripe/webhook', [PaymentController::class, 'webhook']);

// --- Catálogo de productos --------------------------------------------------
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::middleware('auth:api')->group(function () {
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::patch('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    // --- Órdenes de compra ---------------------------------------------
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);

    // --- Pagos (Stripe) ---------------------------------------------------
    Route::post('orders/{order}/pay', [PaymentController::class, 'pay']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
});
