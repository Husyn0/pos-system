<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderItemModifier extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['order_item_id', 'modifier_id', 'price_delta'];
}
