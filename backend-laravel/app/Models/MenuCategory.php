<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'name', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(MenuItem::class, 'category_id')->orderBy('sort_order');
    }
}
