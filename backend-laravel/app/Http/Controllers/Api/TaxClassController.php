<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxClass;
use Illuminate\Http\Request;

class TaxClassController extends Controller
{
    public function index()
    {
        return response()->json(TaxClass::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'rate' => 'required|numeric|min:0|max:100',
            'is_inclusive' => 'nullable|boolean',
        ]);
        return response()->json(TaxClass::create($data), 201);
    }

    public function update(Request $request, TaxClass $taxClass)
    {
        $taxClass->update($request->validate([
            'name' => 'sometimes|string|max:60',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'is_inclusive' => 'sometimes|boolean',
        ]));
        return response()->json($taxClass);
    }
}
