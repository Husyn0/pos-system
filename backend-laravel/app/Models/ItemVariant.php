<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ItemVariant extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['menu_item_id', 'name', 'price_delta', 'sku', 'sort_order'];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
