<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, clear the old default permissions
        DB::table('permissions')->delete();

        // The 18 specific permissions required by the frontend CreateOrgStep2
        $permissions = [
            // Organization Settings
            ['key' => 'org.settings.view', 'label' => 'View Settings', 'group' => 'Organization Settings', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'org.settings.edit', 'label' => 'Edit Settings', 'group' => 'Organization Settings', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'org.profile.edit', 'label' => 'Edit Org Profile', 'group' => 'Organization Settings', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'org.roles.create', 'label' => 'Create Roles', 'group' => 'Organization Settings', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'org.roles.edit', 'label' => 'Edit Role Permissions', 'group' => 'Organization Settings', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'org.members.edit', 'label' => 'Edit Members', 'group' => 'Organization Settings', 'created_at' => now(), 'updated_at' => now()],
            
            // Chat & Communications
            ['key' => 'chat.messages.send', 'label' => 'Send Messages', 'group' => 'Chat & Communications', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'chat.media.download', 'label' => 'Download Media', 'group' => 'Chat & Communications', 'created_at' => now(), 'updated_at' => now()],
            
            // Nodes & Channels
            ['key' => 'node.main.create', 'label' => 'Create Main Nodes', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.sub.create', 'label' => 'Create Sub Nodes / Channels', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.name.edit', 'label' => 'Edit Node Name', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.delete', 'label' => 'Delete Node or Channel', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.members.remove', 'label' => 'Remove Member from Channel', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.join.request', 'label' => 'Request to Join', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.join.auto', 'label' => 'Join Without Request', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'node.join.accept', 'label' => 'Accept Join Requests', 'group' => 'Nodes & Channels', 'created_at' => now(), 'updated_at' => now()],
            
            // Hierarchical Reports
            ['key' => 'report.node.view', 'label' => 'View Node Reports', 'group' => 'Hierarchical Reports', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'report.send', 'label' => 'Send Report', 'group' => 'Hierarchical Reports', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'report.ask', 'label' => 'Ask for Report', 'group' => 'Hierarchical Reports', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('permissions')->insert($permissions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't remove on down to prevent breaking existing roles silently, 
        // as this migration fixes a critical schema parity bug.
    }
};
