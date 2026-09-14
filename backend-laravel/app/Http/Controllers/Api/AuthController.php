<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Staff login: email + password, returns a Sanctum bearer token. */
    public function login(LoginRequest $request)
    {
        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => ['This account is not active.']]);
        }

        $token = $user->createToken($request->device_name ?? 'web-login')->plainTextToken;

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'login',
            'auditable_type' => 'User',
            'auditable_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => $user->load('roles.permissions'),
        ]);
    }

    /** Fast PIN login for a shared POS terminal — requires a paired device token. */
    public function pinLogin(\Illuminate\Http\Request $request)
    {
        $request->validate(['pin' => 'required|digits_between:4,6']);

        $device = app('currentDevice') ?? null;
        if (! $device) {
            return response()->json(['message' => 'This endpoint requires a paired device.'], 403);
        }

        $candidates = User::where('branch_id', $device->branch_id)->where('status', 'active')->get();
        $user = $candidates->first(fn ($u) => $u->pin_hash && Hash::check($request->pin, $u->pin_hash));

        if (! $user) {
            throw ValidationException::withMessages(['pin' => ['Incorrect PIN.']]);
        }

        $token = $user->createToken('pos-pin-login')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function logout(\Illuminate\Http\Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(\Illuminate\Http\Request $request)
    {
        return response()->json($request->user()->load('roles.permissions', 'branch'));
    }
}
