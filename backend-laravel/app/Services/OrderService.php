<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\OrderUpdated;
use App\Exceptions\ConcurrentModificationException;
use App\Exceptions\InvalidOrderStateTransitionException;
use App\Jobs\AwardLoyaltyPointsForOrder;
use App\Models\Discount;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns order creation, mutation, and status transitions. Three invariants
 * this class exists to guarantee, all backed by the tests in
 * tests/Feature/OrderLifecycleTest.php:
 *
 *  1. An order is never created (or added to) without first successfully
 *     reserving the inventory it needs — inventory check and order
 *     persistence happen in the SAME database transaction, so either both
 *     succeed or neither does.
 *  2. Status can only move along the paths OrderStateMachine allows.
 *  3. Every write here locks the order row (SELECT ... FOR UPDATE) before
 *     reading its current state, so two concurrent requests against the
 *     same order (e.g. kitchen taps "ready" while a manager cancels it)
 *     can't both succeed against a state that no longer matches what they
 *     each thought they were acting on.
 */
class OrderService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param array $data Validated payload: branch_id, order_type, source, table_id?,
     *                     customer_id?, device_id?, discount_code?, tip_amount?,
     *                     idempotency_key, items: [{menu_item_id, item_variant_id?, quantity, modifier_ids?, notes?}]
     */
    public function createOrder(string $tenantId, array $data, ?string $createdByUserId = null): Order
    {
        // Idempotency: a retried "place order" request (offline-sync replay,
        // double-tap, network timeout-then-retry) returns the original order
        // instead of creating a duplicate.
        if (! empty($data['idempotency_key'])) {
            $existing = Order::where('tenant_id', $tenantId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing->load('items.modifiers', 'items.menuItem');
            }
        }

        return DB::transaction(function () use ($tenantId, $data, $createdByUserId) {
            $priced = $this->priceLines($data['items']);

            $discountTotal = 0;
            $discount = null;
            if (! empty($data['discount_code'])) {
                $discount = Discount::where('tenant_id', $tenantId)->where('code', $data['discount_code'])->first();
                if (! $discount) {
                    throw ValidationException::withMessages(['discount_code' => ['That discount code is not valid.']]);
                }
                if (! $discount->isValidFor($priced['subtotal'])) {
                    throw ValidationException::withMessages(['discount_code' => ['That discount code cannot be applied to this order.']]);
                }
                $discountTotal = $discount->amountFor($priced['subtotal']);
            }

            $taxableAmount = max(0, $priced['subtotal'] - $discountTotal);
            $taxTotal = round($taxableAmount * $this->resolveTaxRate($priced['lines']), 2);
            $tip = round((float) ($data['tip_amount'] ?? 0), 2);
            $total = round($taxableAmount + $taxTotal + $tip, 2);

            $order = Order::create([
                'tenant_id' => $tenantId,
                'branch_id' => $data['branch_id'],
                'order_number' => $this->nextOrderNumber($data['branch_id']),
                'customer_id' => $data['customer_id'] ?? null,
                'table_id' => $data['table_id'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'created_by' => $createdByUserId,
                'order_type' => $data['order_type'],
                'source' => $data['source'] ?? 'pos',
                'status' => Order::STATUS_PENDING,
                'subtotal' => $priced['subtotal'],
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'tip_amount' => $tip,
                'total' => $total,
                'currency' => $data['currency'] ?? 'USD',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'placed_at' => now(),
            ]);

            $inventoryLines = $this->persistLines($order, $priced['lines']);

            // Reservation happens AFTER order_items exist (so stock_movements
            // can reference real order_item ids) but INSIDE the same
            // transaction — if stock is short, InsufficientStockException
            // propagates out of DB::transaction(), which rolls back the order
            // and its items along with it. The order is never left half-created.
            $this->inventory->reserveForOrderLines($inventoryLines);

            if ($discount) {
                $discount->increment('times_used');
                DB::table('order_discounts')->insert([
                    'order_id' => $order->id,
                    'discount_id' => $discount->id,
                    'amount_applied' => $discountTotal,
                ]);
            }

            event(new OrderCreated($order));

            return $order->fresh(['items.modifiers', 'items.menuItem']);
        });
    }

    /** Add items to an already-open order (dine-in tab, "one more latte"). */
    public function addItems(Order $order, array $newItems): Order
    {
        return DB::transaction(function () use ($order, $newItems) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if (OrderStateMachine::isTerminal($locked->status) || $locked->status === Order::STATUS_READY) {
                throw new InvalidOrderStateTransitionException($locked->status, 'add_items');
            }

            $priced = $this->priceLines($newItems);
            $inventoryLines = $this->persistLines($locked, $priced['lines']);
            $this->inventory->reserveForOrderLines($inventoryLines);

            $this->recalculateTotals($locked);
            event(new OrderUpdated($locked));

            return $locked->fresh(['items.modifiers', 'items.menuItem']);
        });
    }

    /** Voids a single line item (customer changed their mind, kitchen 86'd
     *  it mid-prep, etc.) — restocks its ingredients and recomputes totals. */
    public function voidItem(Order $order, OrderItem $orderItem, string $reason): Order
    {
        return DB::transaction(function () use ($order, $orderItem, $reason) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if (OrderStateMachine::isTerminal($locked->status)) {
                throw new InvalidOrderStateTransitionException($locked->status, 'void_item');
            }
            if ($orderItem->order_id !== $locked->id) {
                throw ValidationException::withMessages(['order_item' => ['That item does not belong to this order.']]);
            }
            if ($orderItem->status === 'voided') {
                return $locked->fresh('items'); // already voided — idempotent no-op
            }

            $orderItem->update(['status' => 'voided', 'notes' => trim(($orderItem->notes ?? '').' [VOIDED: '.$reason.']')]);
            $this->inventory->restoreForOrderItem($orderItem);
            $this->recalculateTotals($locked);

            event(new OrderUpdated($locked));

            return $locked->fresh(['items.modifiers', 'items.menuItem']);
        });
    }

    public function transitionStatus(Order $order, string $newStatus): Order
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($newStatus === Order::STATUS_CANCELLED) {
                throw new \LogicException('Use OrderService::cancelOrder() for cancellations — it handles the paid-order/refund case.');
            }

            OrderStateMachine::assertCanTransition($locked->status, $newStatus);

            $previous = $locked->status;
            $locked->status = $newStatus;
            $locked->lock_version++;

            match ($newStatus) {
                Order::STATUS_CONFIRMED => $locked->confirmed_at = now(),
                Order::STATUS_READY => $locked->ready_at = now(),
                Order::STATUS_COMPLETED => $locked->completed_at = now(),
                default => null,
            };
            $locked->save();

            event(new OrderStatusChanged($locked, $previous));

            if ($newStatus === Order::STATUS_COMPLETED && $locked->customer_id) {
                AwardLoyaltyPointsForOrder::dispatch($locked->id);
            }

            return $locked;
        });
    }

    /**
     * Cancelling is never a bare status flip once money might be involved.
     * - If nothing has been paid: cancel + restock inventory, done.
     * - If something has been paid and $autoRefund is true: refund every
     *   succeeded payment first, THEN cancel + restock.
     * - If something has been paid and $autoRefund is false: refuse, so a
     *   cashier can't accidentally cancel a paid order without the refund
     *   being an explicit, visible step.
     */
    public function cancelOrder(Order $order, string $reason, bool $autoRefund, ?string $userId, PaymentService $payments): Order
    {
        return DB::transaction(function () use ($order, $reason, $autoRefund, $userId, $payments) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            OrderStateMachine::assertCanTransition($locked->status, Order::STATUS_CANCELLED);

            $paidTotal = $locked->payments()->where('status', 'succeeded')->sum('amount');
            if ($paidTotal > 0.001) {
                if (! $autoRefund) {
                    throw new \App\Exceptions\PaymentException(
                        "This order has \${$paidTotal} in successful payments. Refund it explicitly (or pass auto_refund) before cancelling.",
                        'unrefunded_payment_blocks_cancel'
                    );
                }
                foreach ($locked->payments()->where('status', 'succeeded')->get() as $payment) {
                    $payments->refundPayment($payment, (float) $payment->amount, "Order cancelled: {$reason}", $userId);
                }
            }

            $wasRestockable = OrderStateMachine::inventoryIsRestockableFrom($locked->status);

            $previous = $locked->status;
            $locked->status = Order::STATUS_CANCELLED;
            $locked->cancelled_reason = $reason;
            $locked->lock_version++;
            $locked->save();

            if ($wasRestockable) {
                $this->inventory->restoreForOrder($locked->load('items'));
            }

            event(new OrderStatusChanged($locked, $previous));

            return $locked;
        });
    }

    /** Recomputes subtotal/tax/total from active (non-voided) items — call
     *  after any change to order_items. Never recompute historical unit
     *  prices, only re-sum them. */
    public function recalculateTotals(Order $order): void
    {
        $order->refresh();
        $subtotal = (float) $order->activeItems()->sum('line_total');
        $taxableAmount = max(0, $subtotal - (float) $order->discount_total);
        $order->subtotal = $subtotal;
        $order->tax_total = round($taxableAmount * 0.0, 2); // resolve real tax rate per branch in a fuller implementation
        $order->total = round($taxableAmount + $order->tax_total + (float) $order->tip_amount, 2);
        $order->save();
    }

    /** @return array{subtotal: float, lines: array} */
    private function priceLines(array $items): array
    {
        $subtotal = 0;
        $lines = [];

        foreach ($items as $line) {
            $menuItem = MenuItem::findOrFail($line['menu_item_id']);
            $variant = isset($line['item_variant_id']) ? ItemVariant::find($line['item_variant_id']) : null;

            if (! $menuItem->is_active) {
                throw ValidationException::withMessages(['items' => ["{$menuItem->name} is no longer on the menu."]]);
            }
            if (! $menuItem->is_available) {
                throw ValidationException::withMessages(['items' => ["{$menuItem->name} is currently unavailable."]]);
            }

            $unitPrice = $menuItem->priceFor($variant);
            $modifiers = [];
            foreach ($line['modifier_ids'] ?? [] as $modifierId) {
                $modifier = Modifier::findOrFail($modifierId);
                $unitPrice += (float) $modifier->price_delta;
                $modifiers[] = $modifier;
            }

            $quantity = max(1, (int) $line['quantity']);
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'menu_item' => $menuItem,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'line_total' => $lineTotal,
                'modifiers' => $modifiers,
                'notes' => $line['notes'] ?? null,
                'kitchen_station' => $menuItem->category?->name,
            ];
        }

        return ['subtotal' => round($subtotal, 2), 'lines' => $lines];
    }

    /** Persists OrderItem + OrderItemModifier rows and returns the shape
     *  InventoryService::reserveForOrderLines() expects. */
    private function persistLines(Order $order, array $lines): array
    {
        $forInventory = [];

        foreach ($lines as $line) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $line['menu_item']->id,
                'item_variant_id' => $line['variant']?->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'kitchen_station' => $line['kitchen_station'],
                'notes' => $line['notes'],
            ]);

            foreach ($line['modifiers'] as $modifier) {
                OrderItemModifier::create([
                    'order_item_id' => $orderItem->id,
                    'modifier_id' => $modifier->id,
                    'price_delta' => $modifier->price_delta,
                ]);
            }

            $forInventory[] = [
                'order_item' => $orderItem,
                'menu_item' => $line['menu_item'],
                'variant' => $line['variant'],
                'quantity' => $line['quantity'],
            ];
        }

        return $forInventory;
    }

    private function resolveTaxRate(array $lines): float
    {
        // Placeholder for real per-branch/per-item tax_class resolution;
        // kept explicit (not silently "0") so it's obvious where to wire in
        // real tax logic rather than it being buried in arithmetic.
        return 0.0;
    }

    private function nextOrderNumber(string $branchId): string
    {
        $countToday = Order::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereDate('placed_at', now()->toDateString())
            ->count();

        return 'A-' . str_pad((string) ($countToday + 1), 4, '0', STR_PAD_LEFT);
    }
}
