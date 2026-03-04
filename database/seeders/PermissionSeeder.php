<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['key' => 'org.view', 'label' => 'View Organization', 'group' => 'Organization'],
            ['key' => 'org.edit', 'label' => 'Edit Organization', 'group' => 'Organization'],
            ['key' => 'member.invite', 'label' => 'Invite Member', 'group' => 'Members'],
            ['key' => 'member.remove', 'label' => 'Remove Member', 'group' => 'Members'],
            ['key' => 'member.assignRole', 'label' => 'Assign Role', 'group' => 'Members'],
            ['key' => 'role.create', 'label' => 'Create Role', 'group' => 'Roles'],
            ['key' => 'role.edit', 'label' => 'Edit Role', 'group' => 'Roles'],
            ['key' => 'chat.send', 'label' => 'Send Chat', 'group' => 'Chat'],
            ['key' => 'chat.download', 'label' => 'Download Chat', 'group' => 'Chat'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [
                    'label' => $permission['label'],
                    'group' => $permission['group'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
