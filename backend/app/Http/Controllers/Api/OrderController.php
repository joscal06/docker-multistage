<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    #[OA\Get(
        path: '/api/orders',
        summary: 'Historial de compras del cliente autenticado',
        description: 'Lista, de forma paginada, las órdenes del cliente autenticado (más recientes primero).',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Resultados por página (máx. 100)', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historial de órdenes',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): JsonResponse
    {
        $perPage = min((int) request()->input('per_page', 15), 100) ?: 15;

        $orders = Order::query()
            ->where('user_id', Auth::id())
            ->with(['items.product', 'payment'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/orders',
        summary: 'Crear una orden de compra',
        description: 'Crea una orden a partir de un carrito de productos y cantidades. Valida stock disponible '
            .'y descuenta el inventario de forma atómica.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'product_id', type: 'integer', example: 3),
                        new OA\Property(property: 'quantity', type: 'integer', example: 2),
                    ], type: 'object')),
                    new OA\Property(property: 'notes', type: 'string', example: 'Entregar en horario de oficina.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Orden creada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Orden creada exitosamente.'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
            ])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Stock insuficiente para uno o más productos', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($request) {
                $order = Order::create([
                    'user_id' => Auth::id(),
                    'status' => Order::STATUS_PENDING,
                    'total' => 0,
                    'notes' => $request->validated('notes'),
                ]);

                foreach ($request->validated('items') as $item) {
                    /** @var Product $product */
                    $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);

                    if (! $product->hasStock($item['quantity'])) {
                        throw new \RuntimeException("Stock insuficiente para el producto «{$product->name}». Disponible: {$product->stock}.");
                    }

                    $subtotal = round($product->price * $item['quantity'], 2);

                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'subtotal' => $subtotal,
                    ]);

                    $product->decrement('stock', $item['quantity']);
                }

                $order->recalculateTotal();

                return $order;
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(
            new OrderResource($order->load(['items.product', 'payment'])),
            'Orden creada exitosamente.',
            201
        );
    }

    #[OA\Get(
        path: '/api/orders/{order}',
        summary: 'Ver el detalle de una orden',
        description: 'Muestra el detalle de una orden propia, incluyendo sus productos y el estado del pago.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, description: 'ID de la orden', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de la orden', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
            ])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'La orden no pertenece al cliente autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Orden no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Order $order): JsonResponse
    {
        if ($order->user_id !== Auth::id()) {
            throw new AuthorizationException('No tienes permiso para ver esta orden.');
        }

        return ApiResponse::success(new OrderResource($order->load(['items.product', 'payment'])));
    }
}
