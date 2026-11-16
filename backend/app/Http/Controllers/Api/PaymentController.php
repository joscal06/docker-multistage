<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Services\StripePaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController extends Controller
{
    public function __construct(protected StripePaymentService $stripe) {}

    #[OA\Post(
        path: '/api/orders/{order}/pay',
        summary: 'Iniciar el cobro de una orden con Stripe',
        description: 'Crea un Payment Intent de Stripe para el total de la orden y devuelve el `client_secret` '
            .'que el frontend/app móvil debe usar para confirmar el pago (Stripe.js / Stripe SDK). '
            .'El estado final del pago se confirma vía webhook.',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, description: 'ID de la orden a pagar', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Intento de pago creado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Intento de pago creado. Confirma el pago con el client_secret.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                    new OA\Property(property: 'client_secret', type: 'string', example: 'pi_3Nk..._secret_...'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'La orden no pertenece al cliente autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'La orden ya fue pagada o no está en un estado válido para el cobro', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'Error de comunicación con Stripe', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function pay(Order $order): JsonResponse
    {
        if ($order->user_id !== Auth::id()) {
            throw new AuthorizationException('No tienes permiso para pagar esta orden.');
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return ApiResponse::error("La orden se encuentra en estado «{$order->status}» y no puede ser cobrada nuevamente.", 409);
        }

        try {
            $payment = $this->stripe->createPaymentIntentForOrder($order);
        } catch (StripeExceptionInterface $e) {
            Log::error('Stripe payment intent creation failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return ApiResponse::error('No fue posible iniciar el pago con Stripe: '.$e->getMessage(), 502);
        }

        $intent = $payment->raw_response;

        return ApiResponse::success([
            'payment' => new PaymentResource($payment),
            'client_secret' => $intent['client_secret'] ?? null,
        ], 'Intento de pago creado. Confirma el pago con el client_secret.', 201);
    }

    #[OA\Get(
        path: '/api/payments/{payment}',
        summary: 'Consultar / sincronizar el estado de un pago',
        description: 'Devuelve el estado local de un pago y lo sincroniza contra Stripe si aún está pendiente. '
            .'Útil para verificar el resultado del pago desde la app cliente sin depender únicamente del webhook.',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, description: 'ID del pago', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado del pago', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
            ])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'El pago no pertenece al cliente autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Payment $payment): JsonResponse
    {
        if ($payment->order->user_id !== Auth::id()) {
            throw new AuthorizationException('No tienes permiso para ver este pago.');
        }

        if ($payment->status === Payment::STATUS_PENDING) {
            try {
                $payment = $this->stripe->syncPaymentStatus($payment);
            } catch (StripeExceptionInterface $e) {
                Log::warning('Stripe payment sync failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return ApiResponse::success(new PaymentResource($payment));
    }

    #[OA\Post(
        path: '/api/stripe/webhook',
        summary: 'Webhook de eventos de Stripe',
        description: 'Endpoint público (sin JWT) que recibe eventos de Stripe (`payment_intent.succeeded`, '
            .'`payment_intent.payment_failed`, etc.), valida la firma con `STRIPE_WEBHOOK_SECRET` y actualiza '
            .'el estado de los pagos y órdenes correspondientes.',
        tags: ['Payments'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(description: 'Payload de evento de Stripe (raw)')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Evento procesado correctamente'),
            new OA\Response(response: 400, description: 'Firma inválida o payload malformado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function webhook(Request $request): JsonResponse
    {
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = $this->stripe->constructWebhookEvent($request->getContent(), $signature);
        } catch (UnexpectedValueException $e) {
            Log::warning('Stripe webhook: invalid payload', ['error' => $e->getMessage()]);

            return ApiResponse::error('Payload inválido.', 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature', ['error' => $e->getMessage()]);

            return ApiResponse::error('Firma del webhook inválida.', 400);
        }

        $object = $event->data->object;

        if (in_array($event->type, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
            $payment = Payment::query()->where('stripe_payment_intent_id', $object->id)->first();

            if ($payment) {
                $this->stripe->applyIntentToPayment($object, $payment);
            } else {
                Log::warning('Stripe webhook: payment not found for intent', ['intent_id' => $object->id]);
            }
        }

        return ApiResponse::success(null, 'Evento procesado.');
    }
}
