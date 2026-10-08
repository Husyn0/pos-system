<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Http\Request;

class ModifierGroupController extends Controller
{
    public function index()
    {
        return response()->json(ModifierGroup::with('modifiers')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'min_select' => 'nullable|integer|min:0',
            'max_select' => 'nullable|integer|min:1',
            'is_required' => 'nullable|boolean',
            'modifiers' => 'nullable|array',
            'modifiers.*.name' => 'required_with:modifiers|string|max:100',
            'modifiers.*.price_delta' => 'required_with:modifiers|numeric',
        ]);

        $group = ModifierGroup::create(collect($data)->except('modifiers')->toArray());
        foreach ($data['modifiers'] ?? [] as $m) {
            $group->modifiers()->create($m);
        }

        return response()->json($group->load('modifiers'), 201);
    }

    public function attachToItem(Request $request, ModifierGroup $modifierGroup)
    {
        $request->validate(['menu_item_id' => 'required|uuid']);
        \DB::table('menu_item_modifier_groups')->insertOrIgnore([
            'menu_item_id' => $request->menu_item_id,
            'modifier_group_id' => $modifierGroup->id,
        ]);
        return response()->json(['ok' => true]);
    }

    public function addModifier(Request $request, ModifierGroup $modifierGroup)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'price_delta' => 'required|numeric']);
        return response()->json($modifierGroup->modifiers()->create($data), 201);
    }

    public function removeModifier(ModifierGroup $modifierGroup, Modifier $modifier)
    {
        $modifier->delete();
        return response()->json(null, 204);
    }
}
