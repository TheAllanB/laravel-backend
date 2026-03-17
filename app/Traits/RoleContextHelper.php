<?php

namespace App\Traits;

use App\Models\Node;

trait RoleContextHelper
{
    // Helper to deeply verify permissions per organization.
    protected function hasPermission($user, $orgId, $permissionKey)
    {
        $roles = $user->roles()->wherePivot('organization_id', $orgId)->get();
        foreach ($roles as $role) {
            if ($role->permissions()->where('key', $permissionKey)->exists()) {
                return true;
            }
        }
        return false;
    }

    // Helper to get all descendant node IDs (optionally strictly downstream or inclusive)
    protected function getDescendantNodeIds($nodeIds, $orgId, $includeSelf = true)
    {
        if (empty($nodeIds)) return [];
        $ids = $includeSelf ? $nodeIds : [];
        $current = $nodeIds;
        while (!empty($current)) {
            $children = Node::where('organization_id', $orgId)->whereIn('parent_id', $current)->pluck('id')->toArray();
            $current = array_diff($children, $ids);
            if (!empty($current)) {
                $ids = array_merge($ids, $current);
            }
        }
        return array_unique($ids);
    }

    protected function getActiveRoleContext($user, $organizationId, $request) {
        $roleId = $request->input('active_role_id') ?? $request->query('role_id');
        if ($roleId) {
            return $user->roles()->wherePivot('organization_id', $organizationId)->where('roles.id', $roleId)->first();
        }
        return $user->roles()->wherePivot('organization_id', $organizationId)->first();
    }
}
