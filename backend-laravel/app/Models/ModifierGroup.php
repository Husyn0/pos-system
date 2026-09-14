<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    use HasUuids, BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'name', 'min_select', 'max_select', 'is_required'];
    protected $casts = ['is_required' => 'boolean'];

    public function modifiers()
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order');
    }
}
