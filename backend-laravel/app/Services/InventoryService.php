<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\RecipeIngredient;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns every stock-affecting operation. The two rules that make this
 * "stringent" rather than "best effort":
 *
 *  1. Every read-then-write against inventory_items happens inside a DB
 *     transaction with SELECT ... FOR UPDATE (lockForUpdate), and rows are
 *     always locked in a stable, sorted order (by id) across every call
 *     site. That combination is what actually prevents two concurrent
 *     orders from both "seeing" the last croissant and both succeeding —
 *     and the sorted lock order is what prevents two such transactions
 *     from deadlocking each other when an order needs multiple ingredients.
 *
 *  2. Every stock mutation writes an immutable stock_movements row in the
 *     same transaction as the quantity_on_hand update. The running total
 *     is a cache of that ledger, never the other way around — if the two
 *     ever disagree, the ledger is what's trusted.
 *
 * Callers (OrderService) are expected to already be inside DB::transaction()
 * when they call reserve()/restock() — this class does not open its own
 * outer transaction, since inventory changes need to commit atomically
 * with the order/order_items rows they're tied to.
 */
class InventoryService
{
    /**
     * Merges an item's *base* recipe (applies to every variant) with the
     * *variant-specific* recipe (e.g. "Large" needs an extra espresso shot
     * on top of the base Latte recipe). Both can legitimately exist for
     * the same order line at once.
     */
    public function resolveRecipe(MenuItem $menuItem, ?ItemVariant $variant): Collection
    {
        $base = RecipeIngredient::where('menu_item_id', $menuItem->id)->get();
        $variantSpecific = $variant
            ? RecipeIngredient::where('item_variant_id', $variant->id)->get()
            : collect();

        return $base->concat($variantSpecific);
    }

    /**
     * Given the order's line items (already persisted, so we have real
     * order_item ids to attribute consumption to), aggregates total
     * ingredient demand, locks exactly those inventory rows in sorted
     * order, verifies sufficiency, decrements, and writes one
     * stock_movements row per (order_item, ingredient) for full
     * traceability. Throws InsufficientStockException — which the caller's
     * transaction will let bubble up and roll everything back — if *any*
     * ingredient is short, listing every shortage at once.
     *
     * @param  array<int, array{order_item: OrderItem, menu_item: MenuItem, variant: ?ItemVariant, quantity: int}>  $lines
     */
    public function reserveForOrderLines(array $lines): void
    {
        // ingredient_id => ['name' => ..., 'unit' => ..., 'consumption' => [[order_item_id, qty], ...]]
        $demand = [];

        foreach ($lines as $line) {
            $recipe = $this->resolveRecipe($line['menu_item'], $line['variant']);
            foreach ($recipe as $ingredient) {
                $qty = (float) $ingredient->quantity_required * $line['quantity'];
                $id = $ingredient->inventory_item_id;
                $demand[$id]['consumption'][] = ['order_item_id' => $line['order_item']->id, 'qty' => $qty];
                $demand[$id]['total'] = ($demand[$id]['total'] ?? 0) + $qty;
            }
        }

        if (empty($demand)) {
            return; // nothing in this order tracks inventory — nothing to do
        }

        // Sorted lock order across ALL call sites (see class docblock) —
        // this specific line is what prevents cross-order deadlocks.
        $ids = collect(array_keys($demand))->sort()->values()->all();

        /** @var Collection<string, InventoryItem> $items */
        $items = InventoryItem::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

        $shortages = [];
        foreach ($demand as $inventoryItemId => $info) {
            $item = $items->get($inventoryItemId);
            if (! $item) {
                continue; // ingredient referenced by a recipe but deleted from inventory — don't hard-fail the sale on a data hygiene issue
            }
            if ((float) $item->quantity_on_hand < $info['total'] - 0.0001) {
                $shortages[] = [
                    'inventory_item_id' => $item->id,
                    'name' => $item->name,
                    'required' => round($info['total'], 3),
                    'available' => (float) $item->quantity_on_hand,
                    'unit' => $item->unit,
                ];
            }
        }

        if (! empty($shortages)) {
            throw new InsufficientStockException($shortages);
        }

        $now = now();
        $movementRows = [];
        foreach ($demand as $inventoryItemId => $info) {
            $item = $items->get($inventoryItemId);
            if (! $item) continue;

            $item->decrement('quantity_on_hand', $info['total']);

            foreach ($info['consumption'] as $consumption) {
                $movementRows[] = [
                    'inventory_item_id' => $inventoryItemId,
                    'type' => 'sale',
                    'quantity' => -$consumption['qty'],
                    'reference_type' => 'order_item',
                    'reference_id' => $consumption['order_item_id'],
                    'note' => null,
                    'created_by' => null,
                    'created_at' => $now,
                ];
            }
        }
        StockMovement::insert($movementRows);
    }

    /**
     * Reverses a single order_item's stock consumption — used when an
     * item is voided pre-completion, or a whole order is cancelled before
     * it reached 'completed'. Idempotent: if this order_item was already
     * restocked (a 'return' movement already exists for it), does nothing,
     * so a retried cancel request can never double-restock.
     */
    public function restoreForOrderItem(OrderItem $orderItem): void
    {
        $alreadyRestored = StockMovement::where('reference_type', 'order_item')
            ->where('reference_id', $orderItem->id)
            ->where('type', 'return')
            ->exists();
        if ($alreadyRestored) {
            return;
        }

        $consumed = StockMovement::where('reference_type', 'order_item')
            ->where('reference_id', $orderItem->id)
            ->where('type', 'sale')
            ->get();

        if ($consumed->isEmpty()) {
            return;
        }

        $ids = $consumed->pluck('inventory_item_id')->unique()->sort()->values()->all();
        $items = InventoryItem::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

        $now = now();
        $rows = [];
        foreach ($consumed as $movement) {
            $item = $items->get($movement->inventory_item_id);
            if (! $item) continue;

            $restoreQty = abs((float) $movement->quantity);
            $item->increment('quantity_on_hand', $restoreQty);

            $rows[] = [
                'inventory_item_id' => $movement->inventory_item_id,
                'type' => 'return',
                'quantity' => $restoreQty,
                'reference_type' => 'order_item',
                'reference_id' => $orderItem->id,
                'note' => 'Restocked: order cancelled before completion',
                'created_by' => null,
                'created_at' => $now,
            ];
        }
        StockMovement::insert($rows);
    }

    public function restoreForOrder(\App\Models\Order $order): void
    {
        foreach ($order->items as $item) {
            $this->restoreForOrderItem($item);
        }
    }
}
