<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchasingService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchasingService $purchasing) {}

    public function index(Request $request)
    {
        return response()->json(
            PurchaseOrder::with('purchaseOrderItems', 'supplier')
                ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
                ->when($request->status, fn ($q) => $q->where('status', $request->status))
                ->latest('id')
                ->paginate(25)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|uuid',
            'supplier_id' => 'nullable|uuid',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $po = $this->purchasing->createDraft(app('currentTenantId'), [...$data, 'created_by' => $request->user()?->id]);
        return response()->json($po, 201);
    }

    public function markOrdered(PurchaseOrder $purchaseOrder)
    {
        return response()->json($this->purchasing->markOrdered($purchaseOrder));
    }

    public function receive(PurchaseOrder $purchaseOrder)
    {
        return response()->json($this->purchasing->receive($purchaseOrder));
    }
}
