<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $items = InventoryItem::when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->boolean('low_stock_only'), fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'reorder_level'))
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|uuid',
            'name' => 'required|string|max:150',
            'unit' => 'required|string|max:20',
            'quantity_on_hand' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|uuid',
        ]);

        return response()->json(InventoryItem::create($data), 201);
    }

    /** Manual stock adjustment (stocktake correction, waste, etc.) — always logged. */
    public function adjust(Request $request, InventoryItem $inventoryItem)
    {
        $data = $request->validate([
            'quantity_delta' => 'required|numeric', // positive = add, negative = remove
            'type' => 'required|in:purchase,waste,adjustment,transfer',
            'note' => 'nullable|string|max:255',
        ]);

        $inventoryItem->increment('quantity_on_hand', $data['quantity_delta']);

        StockMovement::create([
            'inventory_item_id' => $inventoryItem->id,
            'type' => $data['type'],
            'quantity' => $data['quantity_delta'],
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($inventoryItem->fresh());
    }

    public function movements(InventoryItem $inventoryItem)
    {
        return response()->json($inventoryItem->stockMovements()->latest('created_at')->paginate(50));
    }
}
