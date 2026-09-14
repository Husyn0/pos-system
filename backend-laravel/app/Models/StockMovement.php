<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['inventory_item_id', 'type', 'quantity', 'reference_type', 'reference_id', 'note', 'created_by'];
}
