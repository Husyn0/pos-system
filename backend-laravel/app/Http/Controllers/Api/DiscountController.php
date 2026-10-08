<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index()
    {
        return response()->json(Discount::orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'nullable|string|max:40',
            'name' => 'required|string|max:100',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        return response()->json(Discount::create($data), 201);
    }

    public function update(Request $request, Discount $discount)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'value' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'ends_at' => 'sometimes|nullable|date',
        ]);
        $discount->update($data);
        return response()->json($discount);
    }

    public function destroy(Discount $discount)
    {
        $discount->update(['is_active' => false]); // never hard-delete — order_discounts references it historically
        return response()->json(null, 204);
    }
}
