<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Modifier extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['modifier_group_id', 'name', 'price_delta', 'sort_order'];
}
