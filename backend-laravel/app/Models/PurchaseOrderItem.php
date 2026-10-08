<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['purchase_order_id', 'inventory_item_id', 'quantity', 'unit_cost'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
