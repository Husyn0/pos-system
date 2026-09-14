<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'address_line', 'city', 'country',
        'latitude', 'longitude', 'phone', 'timezone', 'opening_hours', 'is_active',
    ];

    protected $casts = ['opening_hours' => 'array', 'is_active' => 'boolean'];

    public function tables()
    {
        return $this->hasMany(DiningTable::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
