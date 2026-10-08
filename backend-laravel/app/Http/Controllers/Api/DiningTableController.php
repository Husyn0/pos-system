<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DiningTableController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            DiningTable::when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
                ->orderBy('label')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|uuid',
            'label' => 'required|string|max:20',
            'capacity' => 'required|integer|min:1|max:50',
        ]);

        return response()->json(DiningTable::create([
            ...$data,
            'tenant_id' => app('currentTenantId'),
            'qr_token' => Str::random(32),
        ]), 201);
    }

    public function updateStatus(Request $request, DiningTable $diningTable)
    {
        $request->validate(['status' => [Rule::in(['available', 'occupied', 'reserved', 'cleaning'])]]);
        $diningTable->update(['status' => $request->status]);
        return response()->json($diningTable);
    }

    public function destroy(DiningTable $diningTable)
    {
        if ($diningTable->orders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            return response()->json(['message' => 'This table has an open order and cannot be removed.'], 422);
        }
        $diningTable->delete();
        return response()->json(null, 204);
    }
}
