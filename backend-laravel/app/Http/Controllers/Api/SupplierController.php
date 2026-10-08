<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return response()->json(Supplier::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
        ]);
        return response()->json(Supplier::create($data), 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($request->validate([
            'name' => 'sometimes|string|max:150',
            'contact_name' => 'sometimes|nullable|string|max:150',
            'email' => 'sometimes|nullable|email|max:150',
            'phone' => 'sometimes|nullable|string|max:30',
        ]));
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(null, 204);
    }
}
