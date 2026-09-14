<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashDrawerSession extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = [
        'shift_id', 'device_id', 'opening_amount', 'closing_amount',
        'expected_amount', 'difference', 'opened_at', 'closed_at',
    ];
    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function transactions()
    {
        return $this->hasMany(CashDrawerTransaction::class);
    }
}
