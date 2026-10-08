<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs immediately after auth:sanctum. Cross-checks that the authenticated
 * principal (staff User or Customer) actually belongs to the tenant
 * ResolveTenant resolved for this request. See ResolveTenant's docblock
 * for why this two-step (resolve, then verify) split exists — it's the
 * difference between a self-reported X-Tenant-Id header and an actual
 * authorization guarantee.
 */
class EnsureUserBelongsToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->user();
        $resolvedTenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        if ($principal && $resolvedTenantId && (string) $principal->tenant_id !== (string) $resolvedTenantId) {
            return response()->json(['message' => 'Authenticated account does not belong to this tenant.'], 403);
        }

        return $next($request);
    }
}
