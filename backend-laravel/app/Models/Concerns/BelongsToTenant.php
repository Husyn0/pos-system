<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Applied to every tenant-owned model. Automatically scopes all queries
 * to the currently resolved tenant (see ResolveTenant middleware) and
 * stamps tenant_id on creation. This is the application-layer half of
 * multi-tenant isolation; docs/SECURITY.md describes the DB-layer half
 * (Postgres row-level security).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && app()->bound('currentTenantId')) {
                $model->tenant_id = app('currentTenantId');
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
