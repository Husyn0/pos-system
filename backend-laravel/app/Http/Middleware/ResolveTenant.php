<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Figures out which tenant a request belongs to and binds it into the
 * container as 'currentTenantId' / 'currentTenant', which the
 * BelongsToTenant model trait then uses to scope every query.
 *
 * Resolution order, most-trusted first:
 *   1. Paired device token (X-Device-Token) — the device was explicitly
 *      claimed by a specific tenant/branch at pairing time.
 *   2. Explicit X-Tenant-Id header — how admin-web, mobile-app, and
 *      desktop-pos identify their tenant when they aren't hosted on a
 *      tenant subdomain (which is the normal case for native/API clients,
 *      and for local development). SECURITY: this header is
 *      self-reported and MUST be cross-checked once the user is
 *      authenticated — see EnsureUserBelongsToTenant, which runs right
 *      after auth:sanctum and rejects the request if the authenticated
 *      user/customer doesn't actually belong to the tenant this header
 *      claimed. Without that check, a valid token for Tenant A plus a
 *      forged X-Tenant-Id: tenant-b header could leak Tenant B's data
 *      scoped-but-not-authorized to a Tenant A user.
 *   3. Subdomain (brand.posapp.io) — how the public ordering site resolves
 *      in production.
 *
 * Note this middleware intentionally does NOT fall back to
 * $request->user()->tenant_id — that would require auth:sanctum to have
 * already run, and 'tenant' runs first in the middleware stack (query
 * scoping needs to be active before anything touches the database,
 * including Sanctum's own token lookup). EnsureUserBelongsToTenant is the
 * other half of this design: it runs AFTER auth:sanctum and validates
 * consistency rather than ResolveTenant trying to establish it beforehand.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveFromDeviceToken($request)
            ?? $this->resolveFromTenantHeader($request)
            ?? $this->resolveFromSubdomain($request);

        if (! $tenant) {
            return response()->json(['message' => 'Unable to resolve tenant for this request.'], 400);
        }

        if (! $tenant->is_active) {
            return response()->json(['message' => 'This account is currently inactive.'], 403);
        }

        app()->instance('currentTenant', $tenant);
        app()->instance('currentTenantId', $tenant->id);

        return $next($request);
    }

    private function resolveFromDeviceToken(Request $request): ?Tenant
    {
        $token = $request->header('X-Device-Token');
        if (! $token) return null;

        $device = Device::withoutGlobalScopes()->where('device_token', $token)->where('is_active', true)->first();
        if ($device) {
            $device->forceFill(['last_seen_at' => now()])->save();
            app()->instance('currentDevice', $device);
            return Tenant::find($device->tenant_id);
        }
        return null;
    }

    private function resolveFromTenantHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (! $tenantId) return null;

        return Tenant::find($tenantId);
    }

    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $central = config('app.central_domain', 'posapp.io');
        if (! str_ends_with($host, $central)) return null;

        $slug = explode('.', $host)[0] ?? null;
        if (! $slug || $slug === 'www') return null;

        return Tenant::where('slug', $slug)->first();
    }
}
