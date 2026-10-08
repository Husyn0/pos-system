<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $fillable = [
        'tenant_id', 'branch_id', 'supplier_id', 'status', 'ordered_at',
        'received_at', 'total_cost', 'created_by',
    ];
    protected $casts = ['ordered_at' => 'datetime', 'received_at' => 'datetime'];

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
