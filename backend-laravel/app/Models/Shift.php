<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'branch_id', 'user_id', 'clock_in', 'clock_out', 'status'];
    protected $casts = ['clock_in' => 'datetime', 'clock_out' => 'datetime'];

    public function cashDrawerSessions()
    {
        return $this->hasMany(CashDrawerSession::class);
    }
}
