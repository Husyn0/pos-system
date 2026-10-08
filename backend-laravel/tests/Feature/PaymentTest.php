<?php

namespace Tests\Feature;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsPosData;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, SeedsPosData;

    private OrderService $orders;
    private PaymentService $payments;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapTenant();
        $this->orders = app(OrderService::class);
        $this->payments = app(PaymentService::class);

        $item = $this->makeMenuItemWithRecipe('Latte', 4.50);
        $this->order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
    }

    public function test_cash_payment_covering_the_exact_balance_confirms_the_order(): void
    {
        $result = $this->payments->recordPayment($this->order, ['method' => 'cash', 'amount' => 4.50], $this->manager->id);

        $this->assertEquals(0.0, $result['change_due']);
        $this->assertEquals(Order::STATUS_CONFIRMED, $this->order->fresh()->status);
    }

    public function test_cash_overtender_computes_correct_change_without_overcrediting_the_order(): void
    {
        $result = $this->payments->recordPayment($this->order, [
            'method' => 'cash', 'amount' => 4.50, 'tendered_amount' => 20.00,
        ], $this->manager->id);

        $this->assertEquals(15.50, $result['change_due']);
        $this->assertEquals(4.50, (float) $result['payment']->amount); // only the balance is applied, not the full $20 tendered
        $this->assertEquals(4.50, (float) $this->order->fresh()->payments()->sum('amount'));
    }

    public function test_card_payment_without_provider_reference_is_rejected(): void
    {
        $this->expectException(PaymentException::class);
        $this->payments->recordPayment($this->order, ['method' => 'card', 'amount' => 4.50], $this->manager->id);
    }

    public function test_card_payment_exceeding_balance_is_rejected(): void
    {
        $this->expectExceptionMessage('exceeds the remaining balance');
        $this->payments->recordPayment($this->order, [
            'method' => 'card', 'amount' => 100, 'provider_reference' => 'ch_test_123',
        ], $this->manager->id);
    }

    public function test_online_method_is_rejected_and_must_use_the_intent_flow(): void
    {
        $this->expectException(PaymentException::class);
        $this->payments->recordPayment($this->order, ['method' => 'online', 'amount' => 4.50], $this->manager->id);
    }

    public function test_duplicate_idempotency_key_does_not_create_a_second_payment(): void
    {
        $payload = ['method' => 'cash', 'amount' => 4.50, 'idempotency_key' => 'charge-abc'];

        $first = $this->payments->recordPayment($this->order, $payload, $this->manager->id);
        $second = $this->payments->recordPayment($this->order, $payload, $this->manager->id); // simulated retry

        $this->assertEquals($first['payment']->id, $second['payment']->id);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertEquals(1, $this->order->fresh()->payments()->count());
    }

    public function test_paying_an_already_fully_paid_order_is_rejected(): void
    {
        $this->payments->recordPayment($this->order, ['method' => 'cash', 'amount' => 4.50], $this->manager->id);

        $this->expectExceptionMessage('already fully paid');
        $this->payments->recordPayment($this->order, ['method' => 'cash', 'amount' => 1.00], $this->manager->id);
    }

    public function test_refunds_cannot_cumulatively_exceed_the_original_payment(): void
    {
        $result = $this->payments->recordPayment($this->order, ['method' => 'cash', 'amount' => 4.50], $this->manager->id);
        $payment = $result['payment'];

        $this->payments->refundPayment($payment, 3.00, 'partial refund 1', $this->manager->id);
        $this->assertEquals('partially_refunded', $payment->fresh()->status);

        // Only $1.50 is left refundable ($4.50 - $3.00) — asking for $2 must fail,
        // even though $2 alone is "under" the original $4.50 amount.
        $this->expectExceptionMessage('exceeds the');
        $this->payments->refundPayment($payment->fresh(), 2.00, 'partial refund 2', $this->manager->id);
    }

    public function test_fully_refunding_a_payment_marks_it_refunded(): void
    {
        $result = $this->payments->recordPayment($this->order, ['method' => 'cash', 'amount' => 4.50], $this->manager->id);
        $this->payments->refundPayment($result['payment'], 4.50, 'full refund', $this->manager->id);

        $this->assertEquals('refunded', $result['payment']->fresh()->status);
    }

    public function test_paying_a_cancelled_order_is_rejected(): void
    {
        $cancelled = $this->orders->cancelOrder($this->order, 'test cancel', false, $this->manager->id, $this->payments);

        $this->expectExceptionMessage('cancelled');
        $this->payments->recordPayment($cancelled, ['method' => 'cash', 'amount' => 4.50], $this->manager->id);
    }

    public function test_cash_payment_logs_a_cash_drawer_transaction_when_a_session_is_open(): void
    {
        $device = $this->makeDevice();
        $shift = \App\Models\Shift::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'user_id' => $this->manager->id]);
        $session = \App\Models\CashDrawerSession::create(['shift_id' => $shift->id, 'device_id' => $device->id, 'opening_amount' => 100]);

        $this->order->update(['device_id' => $device->id]);
        $this->payments->recordPayment($this->order->fresh(), ['method' => 'cash', 'amount' => 4.50], $this->manager->id);

        $this->assertEquals(1, $session->transactions()->where('type', 'sale')->count());
        $this->assertEquals(4.50, (float) $session->transactions()->first()->amount);
    }
}
