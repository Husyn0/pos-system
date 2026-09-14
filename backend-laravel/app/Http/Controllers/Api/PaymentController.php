<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Records a payment against an order. For 'cash', this is authoritative
     * immediately. For 'card'/'online', the actual card capture already
     * happened at the gateway/terminal — this just records the outcome
     * (see docs/SECURITY.md: no PAN/CVV ever passes through this endpoint).
     */
    public function store(Request $request, Order $order)
    {
        $request->validate([
            'method' => 'required|in:cash,card,wallet,online',
            'provider' => 'nullable|string|max:30',
            'provider_reference' => 'nullable|string|max:150',
            'card_brand' => 'nullable|string|max:20',
            'card_last4' => 'nullable|digits:4',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($request->amount > $order->balanceDue() + 0.01) {
            return response()->json(['message' => 'Payment exceeds the remaining balance.'], 422);
        }

        $payment = Payment::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'method' => $request->method,
            'provider' => $request->provider,
            'provider_reference' => $request->provider_reference,
            'card_brand' => $request->card_brand,
            'card_last4' => $request->card_last4,
            'amount' => $request->amount,
            'status' => 'succeeded', // cash is immediate; gateway payments should verify webhook before calling this
            'processed_by' => $request->user()?->id,
            'paid_at' => now(),
        ]);

        if ($order->fresh()->isFullyPaid() && $order->status === Order::STATUS_PENDING) {
            $order->update(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        }

        return response()->json($payment, 201);
    }

    public function refund(Request $request, Payment $payment)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $payment->amount,
            'reason' => 'required|string|max:255',
        ]);

        $payment->refunds()->create([
            'amount' => $request->amount,
            'reason' => $request->reason,
            'processed_by' => $request->user()?->id,
        ]);

        $payment->update([
            'status' => $request->amount == $payment->amount ? 'refunded' : 'partially_refunded',
        ]);

        return response()->json($payment->fresh('refunds'));
    }

    /** Creates a gateway payment intent for online/card-not-present flows (public-web checkout). */
    public function createIntent(Request $request, Order $order)
    {
        // Wire up Stripe (or another PSP) here, e.g.:
        // $intent = \Stripe\PaymentIntent::create([
        //     'amount' => (int) round($order->balanceDue() * 100),
        //     'currency' => strtolower($order->currency),
        //     'metadata' => ['order_id' => $order->id],
        // ]);
        return response()->json([
            'client_secret' => 'stub_' . Str::random(24),
            'amount' => $order->balanceDue(),
            'currency' => $order->currency,
        ]);
    }
}
