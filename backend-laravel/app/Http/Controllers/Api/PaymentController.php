<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'method' => 'required|in:cash,card,wallet,online',
            'provider' => 'nullable|string|max:30',
            'provider_reference' => 'nullable|string|max:150',
            'card_brand' => 'nullable|string|max:20',
            'card_last4' => 'nullable|digits:4',
            'amount' => 'required|numeric|min:0.01',
            'tendered_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'idempotency_key' => 'nullable|string|max:80',
        ]);

        $result = $this->payments->recordPayment($order, $data, $request->user()?->id);

        return response()->json([
            'payment' => $result['payment'],
            'change_due' => $result['change_due'],
            'idempotent_replay' => $result['idempotent_replay'],
        ], $result['idempotent_replay'] ? 200 : 201);
    }

    public function refund(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $refund = $this->payments->refundPayment($payment, $data['amount'], $data['reason'], $request->user()?->id);

        return response()->json($refund->load('payment'));
    }

    /** Creates a gateway PaymentIntent for online/card-not-present checkout
     *  (public-web). The order isn't marked paid here — only the webhook
     *  (StripeWebhookController), driven by the gateway's own confirmation,
     *  does that. This split is what stops a compromised or buggy client
     *  from ever being able to just *say* a card payment succeeded. */
    public function createIntent(Request $request, Order $order)
    {
        if ($order->isFullyPaid()) {
            return response()->json(['message' => 'This order is already fully paid.'], 422);
        }

        $amount = $order->balanceDue();
        $providerReference = 'pi_stub_' . Str::random(24); // Stripe\PaymentIntent::create(...)->id in production

        Payment::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'method' => 'online',
            'provider' => 'stripe',
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'status' => 'pending', // flips to 'succeeded'/'failed' only via the webhook
        ]);

        return response()->json([
            'client_secret' => $providerReference . '_secret_stub',
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'currency' => $order->currency,
        ]);
    }
}
