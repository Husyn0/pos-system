<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['customer_id', 'order_id', 'points_change', 'reason'];
}
