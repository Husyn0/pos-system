<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids, BelongsToTenant;
    const UPDATED_AT = null;
    protected $fillable = ['tenant_id', 'user_id', 'customer_id', 'channel', 'title', 'body', 'is_read', 'sent_at'];
    protected $casts = ['is_read' => 'boolean', 'sent_at' => 'datetime'];
}
