<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes, BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'name', 'email', 'phone', 'status'];

    protected $hidden = ['password_hash', 'pin_hash', 'two_factor_secret'];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Laravel's auth internals expect getAuthPassword(); we name the column
    // password_hash to be explicit that it is never anything but a hash.
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasPermission(string $key): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('key', $key))
            ->exists();
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
