<?php

$user = App\Models\User::find(1); // 'allan'
$orgId = 6; // 'Reva College'

echo "Roles for user in org $orgId:\n";
$roles = $user->roles()->wherePivot('organization_id', $orgId)->get();
foreach ($roles as $r) {
    echo "- Role ID {$r->id} ({$r->name})\n";
    $hasPerm = $r->permissions()->where('key', 'org.members.edit')->exists();
    echo "   Has 'org.members.edit'? " . ($hasPerm ? 'Yes' : 'No') . "\n";
}

$requests = \App\Models\OrganizationRequest::where('organization_id', $orgId)
    ->where('status', 'pending')
    ->with('user:id,name,handle,email')
    ->get();

echo "\nData length: " . count($requests) . "\n";
echo "Data: " . json_encode($requests) . "\n";
