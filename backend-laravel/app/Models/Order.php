<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'branch_id', 'order_number', 'customer_id', 'table_id', 'device_id',
        'created_by', 'order_type', 'source', 'status', 'subtotal', 'discount_total',
        'tax_total', 'tip_amount', 'total', 'currency', 'idempotency_key', 'placed_at',
        'confirmed_at', 'ready_at', 'completed_at', 'cancelled_reason', 'notes', 'synced_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2',
        'tip_amount' => 'decimal:2', 'total' => 'decimal:2',
        'placed_at' => 'datetime', 'confirmed_at' => 'datetime', 'ready_at' => 'datetime',
        'completed_at' => 'datetime', 'synced_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function amountPaid(): float
    {
        return (float) $this->payments()->where('status', 'succeeded')->sum('amount');
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - $this->amountPaid(), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.001;
    }
}
