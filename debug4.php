<?php
$res = [];
foreach (App\Models\Role::with('permissions')->get() as $role) {
    array_push($res, ['id' => $role->id, 'org_id' => $role->organization_id, 'name' => $role->name, 'has_perms' => $role->permissions->count()]);
}
echo json_encode($res, JSON_PRETTY_PRINT);
