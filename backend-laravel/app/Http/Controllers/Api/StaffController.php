<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            User::with('roles')
                ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'branch_id' => 'nullable|uuid',
            'password' => 'required|string|min:8',
            'pin' => 'nullable|digits_between:4,6',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $user = User::create([
            'tenant_id' => app('currentTenantId'),
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $data['branch_id'] ?? null,
            'password_hash' => Hash::make($data['password']),
            'pin_hash' => isset($data['pin']) ? Hash::make($data['pin']) : null,
            'status' => 'active',
        ]);
        $user->roles()->sync($data['role_ids']);

        return response()->json($user->load('roles'), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:150',
            'branch_id' => 'sometimes|nullable|uuid',
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'invited'])],
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $user->update(collect($data)->except('role_ids')->toArray());
        if (isset($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        return response()->json($user->fresh('roles'));
    }

    /** Staff are deactivated, never hard-deleted — they're referenced by
     *  historical orders/payments/audit logs that must remain intact. */
    public function destroy(User $user)
    {
        $user->update(['status' => 'suspended']);
        $user->tokens()->delete();
        return response()->json(null, 204);
    }

    public function roles()
    {
        return response()->json(Role::with('permissions')->get());
    }
}
