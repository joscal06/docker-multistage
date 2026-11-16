<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Encapsula toda la interacción con la API de Stripe para el procesamiento
 * de pagos de órdenes de compra.
 */
class StripePaymentService
{
    protected ?StripeClient $client = null;

    /**
     * Construye el cliente de Stripe de forma perezosa, para que un error de
     * configuración (p. ej. STRIPE_SECRET vacío) se lance dentro de los
     * métodos públicos —y sea capturable por el controlador— en lugar de
     * romper la inyección de dependencias del propio servicio.
     */
    protected function client(): StripeClient
    {
        return $this->client ??= new StripeClient([
            'api_key' => (string) config('services.stripe.secret'),
            // Evita que la petición quede colgada si Stripe no es alcanzable
            // (p. ej. entorno sin salida a internet o claves no configuradas aún).
            'connect_timeout' => 10,
            'timeout' => 15,
        ]);
    }

    /**
     * Crea (o reutiliza) un Payment Intent de Stripe para una orden y registra
     * el intento de pago en la tabla `payments`.
     *
     * @throws ApiErrorException
     */
    public function createPaymentIntentForOrder(Order $order): Payment
    {
        $amountInCents = (int) round(((float) $order->total) * 100);

        $intent = $this->client()->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => $order->currency,
            'metadata' => [
                'order_id' => (string) $order->id,
                'user_id' => (string) $order->user_id,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return Payment::create([
            'order_id' => $order->id,
            'stripe_payment_intent_id' => $intent->id,
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
            'raw_response' => $intent->toArray(),
        ]);
    }

    /**
     * Consulta el estado actual de un Payment Intent en Stripe y sincroniza
     * el registro local de `payments` / `orders`.
     *
     * @throws ApiErrorException
     */
    public function syncPaymentStatus(Payment $payment): Payment
    {
        $intent = $this->client()->paymentIntents->retrieve($payment->stripe_payment_intent_id);

        $this->applyIntentToPayment($intent, $payment);

        return $payment->fresh();
    }

    /**
     * Verifica la firma de un webhook de Stripe y devuelve el evento parseado.
     *
     * @throws \UnexpectedValueException|SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signatureHeader,
            (string) config('services.stripe.webhook_secret')
        );
    }

    /**
     * Aplica el estado de un Payment Intent de Stripe al registro local de pago
     * y actualiza el estado de la orden asociada.
     */
    public function applyIntentToPayment(PaymentIntent $intent, Payment $payment): void
    {
        $status = match ($intent->status) {
            'succeeded' => Payment::STATUS_SUCCEEDED,
            'canceled' => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };

        $charge = $intent->latest_charge ?? null;

        $payment->update([
            'status' => $status,
            'stripe_charge_id' => is_string($charge) ? $charge : ($charge->id ?? $payment->stripe_charge_id),
            'payment_method' => $intent->payment_method_types[0] ?? $payment->payment_method,
            'failure_reason' => $intent->last_payment_error->message ?? null,
            'raw_response' => $intent->toArray(),
        ]);

        $order = $payment->order;

        if ($status === Payment::STATUS_SUCCEEDED) {
            $order->update(['status' => Order::STATUS_PAID]);
        } elseif ($status === Payment::STATUS_FAILED) {
            $order->update(['status' => Order::STATUS_FAILED]);
        }
    }
}
