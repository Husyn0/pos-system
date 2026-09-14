<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Jobs\DeductInventoryForOrder;
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
 * Owns the order creation / pricing / status-transition logic so that
 * POS, kiosk, mobile, and public-web all go through one consistent,
 * server-trusted calculation of totals rather than trusting client math.
 */
class OrderService
{
    /**
     * @param array $data Validated payload: branch_id, order_type, source, table_id?,
     *                     customer_id?, device_id?, discount_code?, tip_amount?,
     *                     idempotency_key, items: [{menu_item_id, item_variant_id?, quantity, modifier_ids?, notes?}]
     */
    public function createOrder(string $tenantId, array $data, ?string $createdByUserId = null): Order
    {
        // Idempotency: if this exact key was already used, return the existing order
        // instead of creating a duplicate (critical for offline-sync retries).
        if (! empty($data['idempotency_key'])) {
            $existing = Order::where('tenant_id', $tenantId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($tenantId, $data, $createdByUserId) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($data['items'] as $line) {
                $menuItem = MenuItem::findOrFail($line['menu_item_id']);
                $variant = isset($line['item_variant_id']) ? ItemVariant::find($line['item_variant_id']) : null;

                if (! $menuItem->is_available) {
                    throw ValidationException::withMessages([
                        'items' => "{$menuItem->name} is currently unavailable.",
                    ]);
                }

                $unitPrice = $menuItem->priceFor($variant);
                $modifierRows = [];
                foreach ($line['modifier_ids'] ?? [] as $modifierId) {
                    $modifier = Modifier::findOrFail($modifierId);
                    $unitPrice += (float) $modifier->price_delta;
                    $modifierRows[] = $modifier;
                }

                $quantity = max(1, (int) $line['quantity']);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'menu_item' => $menuItem,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'line_total' => $lineTotal,
                    'modifiers' => $modifierRows,
                    'notes' => $line['notes'] ?? null,
                    'kitchen_station' => $menuItem->category?->name ?? null,
                ];
            }

            $discountTotal = 0;
            $discount = null;
            if (! empty($data['discount_code'])) {
                $discount = Discount::where('tenant_id', $tenantId)->where('code', $data['discount_code'])->first();
                if ($discount && $discount->isValidFor($subtotal)) {
                    $discountTotal = $discount->amountFor($subtotal);
                }
            }

            $taxableAmount = max(0, $subtotal - $discountTotal);
            $taxRate = 0.0; // resolve from branch/tax_classes per line item in a fuller implementation
            $taxTotal = round($taxableAmount * $taxRate, 2);
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
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'tip_amount' => $tip,
                'total' => $total,
                'currency' => $data['currency'] ?? 'USD',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($lineItems as $line) {
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
            }

            if ($discount) {
                $order->increment('discount_total', 0); // total already includes it; log usage:
                $discount->increment('times_used');
                DB::table('order_discounts')->insert([
                    'order_id' => $order->id,
                    'discount_id' => $discount->id,
                    'amount_applied' => $discountTotal,
                ]);
            }

            event(new OrderCreated($order));

            return $order->fresh('items.modifiers', 'items.menuItem');
        });
    }

    public function transitionStatus(Order $order, string $newStatus): Order
    {
        $previous = $order->status;
        $order->status = $newStatus;

        match ($newStatus) {
            Order::STATUS_CONFIRMED => $order->confirmed_at = now(),
            Order::STATUS_READY => $order->ready_at = now(),
            Order::STATUS_COMPLETED => $order->completed_at = now(),
            default => null,
        };

        $order->save();

        event(new OrderStatusChanged($order, $previous));

        if ($newStatus === Order::STATUS_COMPLETED) {
            DeductInventoryForOrder::dispatch($order->id);
        }

        return $order;
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
