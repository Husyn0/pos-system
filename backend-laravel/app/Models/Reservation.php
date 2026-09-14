<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasUuids, BelongsToTenant;
    const UPDATED_AT = null;
    protected $fillable = [
        'tenant_id', 'branch_id', 'table_id', 'customer_id', 'guest_name',
        'guest_phone', 'party_size', 'reserved_at', 'status', 'notes',
    ];
    protected $casts = ['reserved_at' => 'datetime'];
}
