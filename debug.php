<?php
$roles = App\Models\Role::with('permissions')->get();
$res = [];
foreach($roles as $r) {
    $res[$r->id] = ['name' => $r->name, 'perms' => $r->permissions->pluck('name')];
}
echo json_encode($res, JSON_PRETTY_PRINT);
