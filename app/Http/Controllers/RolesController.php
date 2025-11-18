<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Role;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolesController extends Controller
{
    public function allRoles(Request $request): JsonResponse
    {
        $roles = UserRole::all();
        return ResponseHelper::success($roles->toArray(), 'Roles retrieved successfully.', 200);
    }

    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'role_name' => ['required', 'string', 'max:255', 'unique:roles,role_name'],
        ]);
        $role = Role::create([
            'role_name' => $request->get('role_name'),
            'active' => 1,
        ]);
        $role->save();

        return ResponseHelper::success($role->toArray(), 'Role created successfully.', 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {

        $validatedData = $request->validate([
            'role_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'role_name')->ignore($role->id, 'id'),
            ],
        ]);

        $role->update([
            'role_name' => $validatedData['role_name'],
        ]);

        return ResponseHelper::success($role->toArray(), 'Role updated successfully.', 200);
    }

    public function deleteRole(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
        ]);
        $role = Role::where('uuid', $request->get('uuid'))->first();
        if (!$role) {
            return ResponseHelper::error([], 'Role not found.', 404);
        }
        $role->delete();
        return ResponseHelper::success($role->toArray(), 'Role deleted successfully.', 200);
    }
}
