<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateTransitionException;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsPosData;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase, SeedsPosData;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapTenant();
        $this->orders = app(OrderService::class);
    }

    public function test_creating_an_order_deducts_recipe_ingredients_atomically(): void
    {
        $milk = $this->makeInventoryItem('Milk', onHand: 1000, unit: 'ml');
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 200, unit: 'g');
        $latte = $this->makeMenuItemWithRecipe('Latte', 4.50, [[$milk, 200], [$beans, 18]]);

        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id,
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $latte->id, 'quantity' => 2]],
        ]);

        $this->assertEquals(9.00, (float) $order->total);
        $this->assertEquals(600, (float) $milk->fresh()->quantity_on_hand); // 1000 - 2*200
        $this->assertEquals(164, (float) $beans->fresh()->quantity_on_hand); // 200 - 2*18

        // Every gram consumed is individually traceable back to the order line.
        $this->assertEquals(2, StockMovement::where('inventory_item_id', $milk->id)->where('type', 'sale')->count());
    }

    public function test_variant_specific_ingredients_are_merged_with_the_base_recipe(): void
    {
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 100, unit: 'g');
        $latte = $this->makeMenuItemWithRecipe('Latte', 4.50, [[$beans, 18]]); // base recipe: 18g
        $large = $latte->variants()->create(['name' => 'Large', 'price_delta' => 0.75]);
        \App\Models\RecipeIngredient::create([
            'item_variant_id' => $large->id,
            'inventory_item_id' => $beans->id,
            'quantity_required' => 9, // Large adds another 9g on top of the base 18g
        ]);

        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id,
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $latte->id, 'item_variant_id' => $large->id, 'quantity' => 1]],
        ]);

        $this->assertEquals(73, (float) $beans->fresh()->quantity_on_hand); // 100 - (18 + 9)
        $this->assertEquals(5.25, (float) $order->total);
    }

    public function test_order_creation_is_rejected_and_fully_rolled_back_when_stock_is_insufficient(): void
    {
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 10, unit: 'g'); // not enough for even one
        $latte = $this->makeMenuItemWithRecipe('Latte', 4.50, [[$beans, 18]]);

        $countBefore = Order::withoutGlobalScopes()->count();

        try {
            $this->orders->createOrder($this->tenant->id, [
                'branch_id' => $this->branch->id,
                'order_type' => 'takeaway',
                'items' => [['menu_item_id' => $latte->id, 'quantity' => 1]],
            ]);
            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $e) {
            $this->assertEquals('Espresso Beans', $e->shortages[0]['name']);
        }

        // The transaction rolled back completely — no half-created order.
        $this->assertEquals($countBefore, Order::withoutGlobalScopes()->count());
        $this->assertEquals(10, (float) $beans->fresh()->quantity_on_hand); // untouched
    }

    public function test_second_order_correctly_fails_once_first_order_exhausts_shared_stock(): void
    {
        // Simulates the "two terminals, one last croissant" race: this
        // proves the *outcome* the locking exists to guarantee (no
        // oversell) using two sequential calls, since PHPUnit runs
        // single-process. True parallel-connection lock contention needs
        // an integration test against a running Postgres instance with
        // two real connections — noted in docs/BUSINESS_RULES.md.
        $croissants = $this->makeInventoryItem('Croissant', onHand: 1, unit: 'pcs');
        $item = $this->makeMenuItemWithRecipe('Croissant', 3.75, [[$croissants, 1]]);

        $order1 = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
        $this->assertEquals(0, (float) $croissants->fresh()->quantity_on_hand);

        $this->expectException(InsufficientStockException::class);
        $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
    }

    public function test_idempotency_key_prevents_duplicate_orders_on_retry(): void
    {
        $item = $this->makeMenuItemWithRecipe('Drip Coffee', 2.50);
        $payload = [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'idempotency_key' => 'retry-key-123',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ];

        $first = $this->orders->createOrder($this->tenant->id, $payload);
        $second = $this->orders->createOrder($this->tenant->id, $payload); // simulated network retry

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, Order::withoutGlobalScopes()->where('idempotency_key', 'retry-key-123')->count());
    }

    public function test_voiding_an_item_restocks_its_ingredients_and_recalculates_totals(): void
    {
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 100, unit: 'g');
        $latte = $this->makeMenuItemWithRecipe('Latte', 4.50, [[$beans, 18]]);
        $croissant = $this->makeMenuItemWithRecipe('Croissant', 3.75);

        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [
                ['menu_item_id' => $latte->id, 'quantity' => 1],
                ['menu_item_id' => $croissant->id, 'quantity' => 1],
            ],
        ]);
        $this->assertEquals(82, (float) $beans->fresh()->quantity_on_hand);
        $this->assertEquals(8.25, (float) $order->total);

        $latteLine = $order->items->firstWhere('menu_item_id', $latte->id);
        $updated = $this->orders->voidItem($order, $latteLine, 'Customer changed their mind');

        $this->assertEquals(100, (float) $beans->fresh()->quantity_on_hand); // fully restocked
        $this->assertEquals(3.75, (float) $updated->total); // only the croissant remains
        $this->assertEquals('voided', $latteLine->fresh()->status);
    }

    public function test_illegal_status_transitions_are_rejected(): void
    {
        $item = $this->makeMenuItemWithRecipe('Drip Coffee', 2.50);
        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $this->expectException(InvalidOrderStateTransitionException::class);
        $this->orders->transitionStatus($order, Order::STATUS_COMPLETED); // pending -> completed skips 3 states
    }

    public function test_completed_orders_cannot_be_cancelled_directly(): void
    {
        $item = $this->makeMenuItemWithRecipe('Drip Coffee', 2.50);
        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
        foreach ([Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY, Order::STATUS_COMPLETED] as $status) {
            $order = $this->orders->transitionStatus($order, $status);
        }

        $this->expectException(InvalidOrderStateTransitionException::class);
        $this->orders->cancelOrder($order, 'changed my mind', false, $this->manager->id, app(PaymentService::class));
    }

    public function test_cancelling_a_paid_order_without_auto_refund_is_blocked(): void
    {
        $item = $this->makeMenuItemWithRecipe('Drip Coffee', 2.50);
        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
        $payments = app(PaymentService::class);
        $payments->recordPayment($order, ['method' => 'cash', 'amount' => 2.50], $this->manager->id);

        $this->expectException(\App\Exceptions\PaymentException::class);
        $this->orders->cancelOrder($order->fresh(), 'refund test', false, $this->manager->id, $payments);
    }

    public function test_cancelling_a_paid_order_with_auto_refund_refunds_and_restocks(): void
    {
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 100, unit: 'g');
        $latte = $this->makeMenuItemWithRecipe('Latte', 4.50, [[$beans, 18]]);
        $order = $this->orders->createOrder($this->tenant->id, [
            'branch_id' => $this->branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $latte->id, 'quantity' => 1]],
        ]);
        $payments = app(PaymentService::class);
        $payments->recordPayment($order, ['method' => 'cash', 'amount' => 4.50], $this->manager->id);

        $cancelled = $this->orders->cancelOrder($order->fresh(), 'refund test', true, $this->manager->id, $payments);

        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals(100, (float) $beans->fresh()->quantity_on_hand); // restocked
        $this->assertEquals('refunded', $order->payments()->first()->status);
    }
}
