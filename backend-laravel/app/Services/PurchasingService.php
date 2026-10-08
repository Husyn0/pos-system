<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The inbound half of stock management (sales are the outbound half, in
 * InventoryService). Receiving a purchase order is the other place stock
 * quantities change, so it gets the same treatment: locked rows, an
 * immutable stock_movements entry per line, and a status guard so the
 * same PO can't be received twice.
 */
class PurchasingService
{
    public function createDraft(string $tenantId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'status' => 'draft',
                'created_by' => $data['created_by'] ?? null,
            ]);

            $total = 0;
            foreach ($data['items'] as $line) {
                $po->purchaseOrderItems()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);
                $total += $line['quantity'] * $line['unit_cost'];
            }
            $po->update(['total_cost' => round($total, 2)]);

            return $po->fresh('purchaseOrderItems');
        });
    }

    public function markOrdered(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw ValidationException::withMessages(['status' => ["Only draft purchase orders can be marked as ordered (this one is '{$po->status}')."]]);
        }
        $po->update(['status' => 'ordered', 'ordered_at' => now()]);
        return $po;
    }

    /**
     * Receiving stock: locks every affected inventory_items row (sorted,
     * same discipline as sales — see InventoryService), increments
     * quantity_on_hand, and writes a 'purchase' stock_movements row per
     * line. Guarded so a PO can only be received once — a duplicate
     * "receive" click (or a retried request) can't double-credit stock.
     */
    public function receive(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $locked = PurchaseOrder::lockForUpdate()->with('purchaseOrderItems')->findOrFail($po->id);

            if ($locked->status === 'received') {
                return $locked; // idempotent — already received, no-op rather than an error
            }
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => ['A cancelled purchase order cannot be received.']]);
            }

            $ids = $locked->purchaseOrderItems->pluck('inventory_item_id')->unique()->sort()->values()->all();
            $items = InventoryItem::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $now = now();
            $movements = [];
            foreach ($locked->purchaseOrderItems as $line) {
                $item = $items->get($line->inventory_item_id);
                if (! $item) continue;

                $item->increment('quantity_on_hand', $line->quantity);
                // Receiving stock at a new cost updates the item's costing basis —
                // a fuller implementation might do weighted-average costing instead
                // of last-cost; last-cost is simpler and stated explicitly here
                // rather than silently picked.
                $item->update(['cost_per_unit' => $line->unit_cost]);

                $movements[] = [
                    'inventory_item_id' => $line->inventory_item_id,
                    'type' => 'purchase',
                    'quantity' => $line->quantity,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $locked->id,
                    'note' => "PO received",
                    'created_at' => $now,
                ];
            }
            StockMovement::insert($movements);

            $locked->update(['status' => 'received', 'received_at' => now()]);

            return $locked->fresh('purchaseOrderItems');
        });
    }
}
