<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\CashDrawerSession;
use App\Models\CashDrawerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Every money-moving operation in the system goes through here. Rules
 * this class exists to enforce (each has a corresponding test in
 * tests/Feature/PaymentTest.php):
 *
 *  - A payment can never push an order's paid total past its `total`
 *    (except cash, where "extra" is change handed back, not applied).
 *  - The same idempotency_key on the same order can never create two
 *    payment rows — a retried "charge" request is a no-op, not a double
 *    charge.
 *  - A refund can never exceed a payment's amount MINUS whatever was
 *    already refunded from it — summed across all prior refunds, not just
 *    checked against the original amount (the bug in a naive
 *    implementation: three $10 refunds against a $12 payment should be
 *    rejected on the third, not allowed because each individually is
 *    "under $12").
 *  - Cash payments write a cash_drawer_transactions row when there's an
 *    open drawer session for the device, so end-of-shift reconciliation
 *    has a real trail, not just a payments-table sum.
 *  - "Card" payments recorded through recordPayment() require a
 *    provider_reference (i.e. the terminal/gateway already confirmed
 *    it) — we never mark a card payment 'succeeded' purely because the
 *    client said so. True online/gateway payments are confirmed via the
 *    webhook path (confirmGatewayPayment), not this synchronous one.
 */
class PaymentService
{
    public function recordPayment(Order $order, array $data, ?string $userId): array
    {
        return DB::transaction(function () use ($order, $data, $userId) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === Order::STATUS_CANCELLED) {
                throw new PaymentException('This order was cancelled and can no longer accept payment.', 'order_cancelled');
            }

            $currency = $data['currency'] ?? $lockedOrder->currency;
            if ($currency !== $lockedOrder->currency) {
                throw new PaymentException("Payment currency ({$currency}) does not match order currency ({$lockedOrder->currency}).", 'currency_mismatch');
            }

            // Idempotency: identical (order, key) returns the original payment.
            if (! empty($data['idempotency_key'])) {
                $existing = Payment::where('tenant_id', $lockedOrder->tenant_id)
                    ->where('order_id', $lockedOrder->id)
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return ['payment' => $existing, 'change_due' => 0.0, 'idempotent_replay' => true];
                }
            }

            $method = $data['method'];
            $requestedAmount = round((float) $data['amount'], 2);
            $balanceDue = round((float) $lockedOrder->total - (float) $lockedOrder->payments()->where('status', 'succeeded')->sum('amount'), 2);

            if ($balanceDue <= 0.001) {
                throw new PaymentException('This order is already fully paid.', 'already_paid');
            }

            if ($method === 'online') {
                throw new PaymentException(
                    'Online/gateway payments must go through POST /orders/{order}/payment-intent and be confirmed via webhook, not recorded directly.',
                    'use_gateway_intent_flow'
                );
            }

            $tenderedAmount = null;
            $changeDue = 0.0;
            $amountApplied = $requestedAmount;

            if ($method === 'cash') {
                $tenderedAmount = round((float) ($data['tendered_amount'] ?? $requestedAmount), 2);
                if ($tenderedAmount < $balanceDue - 0.001 && $tenderedAmount < $requestedAmount - 0.001) {
                    throw new PaymentException('Tendered cash amount is less than the requested payment amount.', 'insufficient_tender');
                }
                $amountApplied = min($requestedAmount, $balanceDue);
                $changeDue = round(max(0, $tenderedAmount - $amountApplied), 2);
            } elseif ($method === 'card' || $method === 'wallet') {
                if (empty($data['provider_reference'])) {
                    throw new PaymentException(
                        "A {$method} payment requires provider_reference confirming the terminal/gateway already authorized it — this endpoint does not itself talk to a payment processor.",
                        'missing_provider_reference'
                    );
                }
                if ($requestedAmount > $balanceDue + 0.01) {
                    throw new PaymentException("Payment amount (\${$requestedAmount}) exceeds the remaining balance (\${$balanceDue}).", 'overpayment');
                }
            }

            $payment = Payment::create([
                'tenant_id' => $lockedOrder->tenant_id,
                'order_id' => $lockedOrder->id,
                'method' => $method,
                'provider' => $data['provider'] ?? ($method === 'cash' ? 'cash' : null),
                'provider_reference' => $data['provider_reference'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'card_brand' => $data['card_brand'] ?? null,
                'card_last4' => $data['card_last4'] ?? null,
                'amount' => $amountApplied,
                'tendered_amount' => $tenderedAmount,
                'status' => 'succeeded',
                'processed_by' => $userId,
                'paid_at' => now(),
            ]);

            if ($method === 'cash') {
                $this->logCashDrawerTransaction($lockedOrder, 'sale', $amountApplied);
            }

            $newBalance = round((float) $lockedOrder->total - (float) $lockedOrder->payments()->where('status', 'succeeded')->sum('amount'), 2);
            if ($newBalance <= 0.001 && $lockedOrder->status === Order::STATUS_PENDING) {
                OrderStateMachine::assertCanTransition($lockedOrder->status, Order::STATUS_CONFIRMED);
                $lockedOrder->update(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()]);
                event(new \App\Events\OrderStatusChanged($lockedOrder, Order::STATUS_PENDING));
            }

            return ['payment' => $payment, 'change_due' => $changeDue, 'idempotent_replay' => false];
        });
    }

    public function refundPayment(Payment $payment, float $amount, string $reason, ?string $userId): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $userId) {
            $lockedPayment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== 'succeeded' && $lockedPayment->status !== 'partially_refunded') {
                throw new PaymentException("Cannot refund a payment with status '{$lockedPayment->status}'.", 'not_refundable');
            }

            $alreadyRefunded = round((float) $lockedPayment->refunds()->sum('amount'), 2);
            $refundable = round((float) $lockedPayment->amount - $alreadyRefunded, 2);
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new PaymentException('Refund amount must be greater than zero.', 'invalid_amount');
            }
            if ($amount > $refundable + 0.01) {
                throw new PaymentException(
                    "Refund of \${$amount} exceeds the \${$refundable} still refundable on this payment (\${$alreadyRefunded} already refunded of \${$lockedPayment->amount}).",
                    'refund_exceeds_balance'
                );
            }

            $refund = Refund::create([
                'payment_id' => $lockedPayment->id,
                'amount' => $amount,
                'reason' => $reason,
                'processed_by' => $userId,
            ]);

            $totalRefunded = $alreadyRefunded + $amount;
            $lockedPayment->update([
                'status' => $totalRefunded >= (float) $lockedPayment->amount - 0.001 ? 'refunded' : 'partially_refunded',
            ]);

            if ($lockedPayment->method === 'cash') {
                $order = Order::withoutGlobalScopes()->find($lockedPayment->order_id);
                if ($order) {
                    $this->logCashDrawerTransaction($order, 'refund', -$amount);
                }
            }

            return $refund;
        });
    }

    /** Called from the payment gateway webhook once it independently confirms
     *  success/failure — this is the ONLY path that marks an 'online' payment
     *  'succeeded'. Idempotent against webhook redelivery. */
    public function confirmGatewayPayment(string $providerReference, string $status, ?array $cardInfo = null): ?Payment
    {
        return DB::transaction(function () use ($providerReference, $status, $cardInfo) {
            $payment = Payment::withoutGlobalScopes()->where('provider_reference', $providerReference)->lockForUpdate()->first();
            if (! $payment) {
                return null; // unknown reference — log and investigate at the call site, don't throw into a webhook 500
            }
            if (in_array($payment->status, ['succeeded', 'refunded', 'partially_refunded'], true)) {
                return $payment; // already settled — webhook redelivery is a no-op
            }

            $payment->update([
                'status' => $status,
                'paid_at' => $status === 'succeeded' ? now() : null,
                'card_brand' => $cardInfo['brand'] ?? $payment->card_brand,
                'card_last4' => $cardInfo['last4'] ?? $payment->card_last4,
            ]);

            if ($status === 'succeeded') {
                $order = Order::withoutGlobalScopes()->lockForUpdate()->find($payment->order_id);
                $balance = round((float) $order->total - (float) $order->payments()->where('status', 'succeeded')->sum('amount'), 2);
                if ($balance <= 0.001 && $order->status === Order::STATUS_PENDING) {
                    OrderStateMachine::assertCanTransition($order->status, Order::STATUS_CONFIRMED);
                    $order->update(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()]);
                    event(new \App\Events\OrderStatusChanged($order, Order::STATUS_PENDING));
                }
            }

            return $payment;
        });
    }

    private function logCashDrawerTransaction(Order $order, string $type, float $amount): void
    {
        if (! $order->device_id) return;

        $session = CashDrawerSession::whereHas('shift', fn ($q) => $q->where('branch_id', $order->branch_id))
            ->where('device_id', $order->device_id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        if (! $session) return; // no open drawer session tracked for this device — skip silently, don't block the sale over bookkeeping

        CashDrawerTransaction::create([
            'cash_drawer_session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'reference_order_id' => $order->id,
        ]);
    }
}
