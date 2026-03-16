<?php

$user = App\Models\User::find(1); // Allan
$organizationId = 6;
$permissionKey = 'org.members.edit';

echo "User: " . $user->name . "\n";
$roles = $user->roles()->wherePivot('organization_id', $organizationId)->get();

if ($roles->isEmpty()) {
    echo "NO ROLES IN THIS ORG!\n";
} else {
    $hasPermission = false;
    foreach ($roles as $role) {
        $exists = $role->permissions()->where('key', $permissionKey)->exists();
        echo "Role '{$role->name}' has permission '$permissionKey' ? " . ($exists ? 'YES' : 'NO') . "\n";
        if ($exists) {
            $hasPermission = true;
            break;
        }
    }
    echo "Final Result: " . ($hasPermission ? 'ALLOWED' : 'FORBIDDEN') . "\n";
}
