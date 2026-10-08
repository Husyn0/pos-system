<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        return response()->json(Branch::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:20',
            'address_line' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'timezone' => 'nullable|string|max:60',
        ]);
        return response()->json(Branch::create($data), 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:150',
            'address_line' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'opening_hours' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);
        $branch->update($data);
        return response()->json($branch);
    }

    public function destroy(Branch $branch)
    {
        if ($branch->orders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            return response()->json(['message' => 'This branch has open orders and cannot be deactivated.'], 422);
        }
        $branch->delete(); // soft delete
        return response()->json(null, 204);
    }
}
