<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasUuids, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'type', 'pairing_code',
        'device_token', 'hardware_bridge_url', 'app_version', 'last_seen_at', 'is_active',
    ];

    protected $casts = ['last_seen_at' => 'datetime', 'is_active' => 'boolean'];
    protected $hidden = ['device_token', 'pairing_code'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }
}
