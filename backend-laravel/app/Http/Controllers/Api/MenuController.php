<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertMenuItemRequest;
use App\Models\ItemVariant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /** Public + POS menu read: categories with active, available items nested. */
    public function index(Request $request)
    {
        $categories = MenuCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['items' => function ($q) {
                $q->where('is_active', true)->with('variants', 'modifierGroups.modifiers');
            }])
            ->get();

        return response()->json($categories);
    }

    public function store(UpsertMenuItemRequest $request)
    {
        $item = MenuItem::create($request->only([
            'category_id', 'name', 'description', 'base_price', 'cost_price',
            'sku', 'track_inventory', 'is_available', 'prep_time_minutes',
        ]));

        foreach ($request->input('variants', []) as $variant) {
            $item->variants()->create($variant);
        }

        return response()->json($item->load('variants'), 201);
    }

    public function update(UpsertMenuItemRequest $request, MenuItem $menuItem)
    {
        $menuItem->update($request->only([
            'category_id', 'name', 'description', 'base_price', 'cost_price',
            'sku', 'track_inventory', 'is_available', 'prep_time_minutes',
        ]));

        return response()->json($menuItem->fresh('variants'));
    }

    /** Quick "86 it" toggle used constantly from the POS during service. */
    public function toggleAvailability(MenuItem $menuItem)
    {
        $menuItem->update(['is_available' => ! $menuItem->is_available]);
        return response()->json($menuItem);
    }

    public function destroy(MenuItem $menuItem)
    {
        $menuItem->delete(); // soft delete — historical orders keep referencing it
        return response()->json(null, 204);
    }
}
