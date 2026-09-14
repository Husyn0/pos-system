<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    /** Manager generates a short-lived pairing code shown on the dashboard. */
    public function createPairingCode(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|uuid',
            'name' => 'required|string|max:100',
            'type' => 'required|in:pos_terminal,kds,kiosk,tablet_waiter',
        ]);

        $device = Device::create([
            ...$data,
            'tenant_id' => app('currentTenantId'),
            'pairing_code' => strtoupper(Str::random(6)),
            'is_active' => false,
        ]);

        return response()->json(['pairing_code' => $device->pairing_code, 'device_id' => $device->id]);
    }

    /** The physical device calls this once, using the code shown on screen, to get its permanent token. */
    public function claim(Request $request)
    {
        $request->validate(['pairing_code' => 'required|string']);

        $device = Device::withoutGlobalScopes()->where('pairing_code', strtoupper($request->pairing_code))->firstOrFail();

        $device->update([
            'device_token' => Str::random(64),
            'pairing_code' => null,
            'is_active' => true,
        ]);

        return response()->json(['device_token' => $device->device_token, 'branch_id' => $device->branch_id]);
    }

    public function heartbeat(Request $request)
    {
        $device = app('currentDevice');
        $request->validate(['app_version' => 'nullable|string|max:30', 'hardware_bridge_url' => 'nullable|url']);
        $device?->update([
            'last_seen_at' => now(),
            'app_version' => $request->app_version ?? $device->app_version,
            'hardware_bridge_url' => $request->hardware_bridge_url ?? $device->hardware_bridge_url,
        ]);

        return response()->json(['ok' => true]);
    }
}
