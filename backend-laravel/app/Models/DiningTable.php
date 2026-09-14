<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DiningTable extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $table = 'dining_tables';
    protected $fillable = ['tenant_id', 'branch_id', 'label', 'capacity', 'status', 'qr_token'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }
}
