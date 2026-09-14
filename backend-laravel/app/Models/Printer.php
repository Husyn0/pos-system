<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    use HasUuids, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'device_id', 'name', 'role', 'connection_type',
        'ip_address', 'port', 'usb_identifier', 'paper_width_mm', 'has_cash_drawer',
        'kitchen_station', 'is_active',
    ];

    protected $casts = ['has_cash_drawer' => 'boolean', 'is_active' => 'boolean'];
}
