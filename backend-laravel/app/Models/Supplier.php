<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'name', 'contact_name', 'email', 'phone'];

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
