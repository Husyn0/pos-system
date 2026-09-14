<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashDrawerTransaction extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['cash_drawer_session_id', 'type', 'amount', 'reference_order_id', 'note'];
}
