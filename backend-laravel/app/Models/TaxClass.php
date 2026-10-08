<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TaxClass extends Model
{
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'name', 'rate', 'is_inclusive'];
    protected $casts = ['is_inclusive' => 'boolean'];
}
