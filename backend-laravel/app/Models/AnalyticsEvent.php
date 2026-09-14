<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['tenant_id', 'branch_id', 'event_type', 'payload', 'occurred_at'];
    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];
}
