<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'name', 'email', 'phone', 'marketing_opt_in'];
    protected $hidden = ['password_hash'];
    protected $casts = ['marketing_opt_in' => 'boolean', 'created_at' => 'datetime'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function isGuest(): bool
    {
        return empty($this->password_hash);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
