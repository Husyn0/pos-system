<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasUuids, BelongsToTenant;
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'order_id', 'method', 'provider', 'provider_reference',
        'card_brand', 'card_last4', 'amount', 'status', 'processed_by', 'paid_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
