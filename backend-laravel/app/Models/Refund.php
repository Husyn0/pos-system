<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasUuids;
    const UPDATED_AT = null;
    protected $fillable = ['payment_id', 'amount', 'reason', 'processed_by'];
}
