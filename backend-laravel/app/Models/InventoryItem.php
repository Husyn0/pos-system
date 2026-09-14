<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasUuids, BelongsToTenant;
    const CREATED_AT = null;
    protected $fillable = [
        'tenant_id', 'branch_id', 'supplier_id', 'name', 'unit',
        'quantity_on_hand', 'reorder_level', 'cost_per_unit',
    ];

    public function isLowStock(): bool
    {
        return (float) $this->quantity_on_hand <= (float) $this->reorder_level;
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
