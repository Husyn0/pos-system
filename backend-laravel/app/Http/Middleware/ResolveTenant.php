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
 * Resolution order: paired device token -> subdomain -> authenticated
 * user/customer's own tenant_id.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveFromDeviceToken($request)
            ?? $this->resolveFromSubdomain($request)
            ?? $this->resolveFromAuthenticatedPrincipal($request);

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
            return $device->tenant()->withoutGlobalScopes()->first() ?? Tenant::find($device->tenant_id);
        }
        return null;
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

    private function resolveFromAuthenticatedPrincipal(Request $request): ?Tenant
    {
        $user = $request->user();
        if ($user && isset($user->tenant_id)) {
            return Tenant::find($user->tenant_id);
        }
        return null;
    }
}
