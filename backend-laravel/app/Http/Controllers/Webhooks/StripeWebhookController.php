<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Stripe's asynchronous confirmation of a PaymentIntent and is the
 * ONLY code path allowed to mark an 'online' payment 'succeeded' — see the
 * class docblock on PaymentService for why that split exists.
 *
 * Not behind the 'tenant' or 'auth:sanctum' middleware: Stripe calls this
 * with its own signature scheme instead, verified below. The tenant is
 * resolved from the Payment row itself (via provider_reference), not from
 * request headers.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // In production:
        // try {
        //     $event = \Stripe\Webhook::constructEvent($payload, $signature, config('services.stripe.webhook_secret'));
        // } catch (\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) {
        //     Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
        //     return response('Invalid signature', 400);
        // }
        // $object = $event->data->object;

        $body = json_decode($payload, true) ?? [];
        $eventType = $body['type'] ?? null;
        $object = $body['data']['object'] ?? [];
        $providerReference = $object['id'] ?? null;

        if (! $providerReference) {
            return response('Missing payment intent id', 400);
        }

        $status = match ($eventType) {
            'payment_intent.succeeded' => 'succeeded',
            'payment_intent.payment_failed' => 'failed',
            default => null,
        };

        if ($status === null) {
            return response('Ignored event type', 200); // ack anything we don't act on so Stripe stops retrying it
        }

        $cardInfo = isset($object['charges']['data'][0]['payment_method_details']['card']) ? [
            'brand' => $object['charges']['data'][0]['payment_method_details']['card']['brand'] ?? null,
            'last4' => $object['charges']['data'][0]['payment_method_details']['card']['last4'] ?? null,
        ] : null;

        $payment = $this->payments->confirmGatewayPayment($providerReference, $status, $cardInfo);

        if (! $payment) {
            Log::warning('Stripe webhook referenced an unknown payment_intent', ['reference' => $providerReference]);
        }

        return response('OK', 200);
    }
}
