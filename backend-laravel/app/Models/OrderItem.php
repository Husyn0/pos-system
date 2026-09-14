<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUuids;
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'menu_item_id', 'item_variant_id', 'quantity', 'unit_price',
        'line_total', 'kitchen_station', 'status', 'notes',
    ];

    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant()
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function modifiers()
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
