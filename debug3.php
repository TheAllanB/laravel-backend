<?php
$user = App\Models\User::first();
$id = $user->organizations()->first()->id;

$organization = $user->organizations()->where('organizations.id', $id)->first();
$userRoles = $user->roles()->wherePivot('organization_id', $organization->id)->get();
$activeRole = $userRoles->first();

echo json_encode([
    'organization' => $organization,
    'role' => $activeRole ? ['id' => $activeRole->id, 'name' => $activeRole->name, 'node_id' => $activeRole->pivot->node_id ?? null] : null,
    'permissions' => $activeRole ? $activeRole->permissions()->pluck('key') : [],
], JSON_PRETTY_PRINT);
