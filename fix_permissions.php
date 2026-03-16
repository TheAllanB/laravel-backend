<?php
// 1. Clear out orphaned pivot records first just in case
Illuminate\Support\Facades\DB::table('role_permission')->truncate();

// 2. Get all fresh permission IDs
$allPermissions = App\Models\Permission::pluck('id');

// 3. Find all roles named 'Owner' and sync the permissions
$ownerRoles = App\Models\Role::where('name', 'Owner')->get();

foreach ($ownerRoles as $role) {
    $role->permissions()->sync($allPermissions);
    echo "Synced " . count($allPermissions) . " permissions to role ID {$role->id}\n";
}
