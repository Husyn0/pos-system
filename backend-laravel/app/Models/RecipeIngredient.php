<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['menu_item_id', 'item_variant_id', 'inventory_item_id', 'quantity_required'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
