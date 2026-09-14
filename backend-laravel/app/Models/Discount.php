<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'value', 'min_order_amount',
        'starts_at', 'ends_at', 'usage_limit', 'times_used', 'is_active',
    ];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];

    public function isValidFor(float $orderSubtotal): bool
    {
        if (! $this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->ends_at && now()->gt($this->ends_at)) return false;
        if ($this->usage_limit && $this->times_used >= $this->usage_limit) return false;
        if ($orderSubtotal < (float) $this->min_order_amount) return false;
        return true;
    }

    public function amountFor(float $orderSubtotal): float
    {
        return $this->type === 'percentage'
            ? round($orderSubtotal * ((float) $this->value / 100), 2)
            : min((float) $this->value, $orderSubtotal);
    }
}
