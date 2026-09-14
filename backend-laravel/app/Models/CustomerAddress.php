<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['customer_id', 'label', 'address_line', 'city', 'latitude', 'longitude', 'is_default'];
}
