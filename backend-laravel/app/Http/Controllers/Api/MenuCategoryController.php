<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    public function index()
    {
        return response()->json(MenuCategory::orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);
        return response()->json(MenuCategory::create($data), 201);
    }

    public function update(Request $request, MenuCategory $menuCategory)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);
        $menuCategory->update($data);
        return response()->json($menuCategory);
    }

    public function destroy(MenuCategory $menuCategory)
    {
        if ($menuCategory->items()->exists()) {
            return response()->json(['message' => 'Move or delete items in this category first.'], 422);
        }
        $menuCategory->delete();
        return response()->json(null, 204);
    }
}
