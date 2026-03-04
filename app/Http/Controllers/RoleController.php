<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Get all roles for an organization, including permission groups.
     */
    public function index(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        // Fetch roles with their associated permission IDs
        $roles = Role::where('organization_id', $organizationId)
            ->with('permissions')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_owner' => $role->name === 'Owner',
                    'permissions' => $role->permissions->pluck('key'),
                    'permissions_count' => $role->permissions->count(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'roles' => $roles
        ]);
    }

    /**
     * Get all available system permissions grouped by their category.
     */
    public function permissions()
    {
        $permissions = Permission::all()->groupBy('group')->map(function ($group) {
            return $group->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'key' => $perm->key,
                    'label' => $perm->label,
                ];
            });
        });

        return response()->json([
            'status' => 'success',
            'groups' => $permissions
        ]);
    }

    /**
     * Create a new role for the organization.
     */
    public function store(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,key'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Check if role name already exists in this org
        if (Role::where('organization_id', $organizationId)->where('name', $request->name)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'A role with this name already exists in the organization.'], 422);
        }

        DB::beginTransaction();

        try {
            $role = Role::create([
                'organization_id' => $organizationId,
                'name' => $request->name,
            ]);

            if ($request->has('permissions') && count($request->permissions) > 0) {
                $permissionIds = Permission::whereIn('key', $request->permissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Role created successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_owner' => false,
                    'permissions' => $role->permissions()->pluck('key'),
                    'permissions_count' => count($request->permissions ?? []),
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to create role.'], 500);
        }
    }

    /**
     * Update an existing role's name and permissions.
     */
    public function update(Request $request, $organizationId, $roleId)
    {
        $organization = Organization::findOrFail($organizationId);
        $role = Role::where('organization_id', $organizationId)->findOrFail($roleId);

        if ($role->name === 'Owner') {
            return response()->json(['status' => 'error', 'message' => 'The Owner role cannot be modified.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,key'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Check uniqueness of new name
        if (Role::where('organization_id', $organizationId)->where('name', $request->name)->where('id', '!=', $roleId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'A role with this name already exists in the organization.'], 422);
        }

        DB::beginTransaction();

        try {
            $role->name = $request->name;
            $role->save();

            if ($request->has('permissions')) {
                $permissionIds = Permission::whereIn('key', $request->permissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Role updated successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_owner' => false,
                    'permissions' => $role->permissions()->pluck('key'),
                    'permissions_count' => $role->permissions()->count(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update role.'], 500);
        }
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, $organizationId, $roleId)
    {
        $role = Role::where('organization_id', $organizationId)->findOrFail($roleId);

        if ($role->name === 'Owner') {
            return response()->json(['status' => 'error', 'message' => 'The Owner role cannot be deleted.'], 403);
        }

        $role->delete();

        return response()->json(['status' => 'success', 'message' => 'Role deleted successfully.']);
    }

    /**
     * Assign multiple roles to a user in the organization (supports node_id).
     * Expected payload: roles: [{ "role_id": 1, "node_id": null }, { "role_id": 2, "node_id": 5 }]
     */
    public function assignRoles(Request $request, $organizationId, $userId)
    {
        $organization = Organization::findOrFail($organizationId);
        $user = \App\Models\User::findOrFail($userId);

        // Ensure user is in org
        if (!$user->organizations()->where('organization_id', $organizationId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'User is not a member of this organization.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'roles' => 'present|array',
            'roles.*.role_id' => 'required|exists:roles,id',
            'roles.*.node_id' => 'nullable|exists:nodes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $assignmentData = $request->roles ?? [];

        // Verify roles belong to this org
        $submittedRoleIds = array_column($assignmentData, 'role_id');
        if (count($submittedRoleIds) > 0) {
            $validRoleIds = Role::where('organization_id', $organizationId)->whereIn('id', $submittedRoleIds)->pluck('id')->toArray();
            if (count($validRoleIds) !== count(array_unique($submittedRoleIds))) {
                return response()->json(['status' => 'error', 'message' => 'Invalid roles provided for this organization.'], 422);
            }
        }

        // Verify nodes belong to this org
        $submittedNodeIds = array_filter(array_column($assignmentData, 'node_id'));
        if (count($submittedNodeIds) > 0) {
            $validNodeIds = \App\Models\Node::where('organization_id', $organizationId)->whereIn('id', $submittedNodeIds)->pluck('id')->toArray();
            if (count($validNodeIds) !== count(array_unique($submittedNodeIds))) {
                return response()->json(['status' => 'error', 'message' => 'Invalid nodes provided for this organization.'], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Detach all roles for user in this org
            $user->roles()->wherePivot('organization_id', $organizationId)->detach();

            // Attach new roles respecting node_id
            foreach ($assignmentData as $assignment) {
                // Determine user_id to keep code clean
                DB::table('role_user')->insert([
                    'user_id' => $user->id,
                    'role_id' => $assignment['role_id'],
                    'organization_id' => $organizationId,
                    'node_id' => $assignment['node_id'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Roles assigned successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to assign roles: ' . $e->getMessage()], 500);
        }
    }
}
