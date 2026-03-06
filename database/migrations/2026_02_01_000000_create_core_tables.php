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
        // 1. Create Organizations table
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('uid', 12)->unique();
                $table->string('website')->nullable();
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // 2. Create Roles table
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->string('name');
                $table->timestamps();
            });
        }

        // 3. Create Permissions table
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->string('group');
                $table->timestamps();
            });
        }

        // 4. Create Nodes table
        if (!Schema::hasTable('nodes')) {
            Schema::create('nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->foreignId('parent_id')->nullable()->constrained('nodes')->onDelete('cascade');
                $table->string('name');
                $table->string('type', 50)->default('folder');
                $table->timestamps();
            });

            // Add index for performance
            Schema::table('nodes', function (Blueprint $table) {
                $table->index(['organization_id', 'parent_id'], 'nodes_org_parent_index');
            });
        }

        // 5. Create Organization_User pivot table
        if (!Schema::hasTable('organization_user')) {
            Schema::create('organization_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->timestamp('joined_at')->useCurrent();
                $table->unique(['user_id', 'organization_id'], 'uk_org_user_user_org');
            });
        }

        // 6. Create Role_User pivot table
        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
                $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->unique(['user_id', 'organization_id'], 'uk_role_user_user_org');
            });
        }

        // 7. Create Role_Permission pivot table
        if (!Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
                $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
                $table->unique(['role_id', 'permission_id'], 'uk_role_perm_composite');
            });
        }

        // Seed basic permissions if empty
        if (DB::table('permissions')->count() === 0) {
            DB::table('permissions')->insert([
                ['key' => 'org.view', 'label' => 'View Organization', 'group' => 'Organization', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'org.edit', 'label' => 'Edit Organization', 'group' => 'Organization', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'member.invite', 'label' => 'Invite Member', 'group' => 'Members', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'member.remove', 'label' => 'Remove Member', 'group' => 'Members', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'member.assignRole', 'label' => 'Assign Role', 'group' => 'Members', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'role.create', 'label' => 'Create Role', 'group' => 'Roles', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'role.edit', 'label' => 'Edit Role', 'group' => 'Roles', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'chat.send', 'label' => 'Send Chat', 'group' => 'Chat', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'chat.download', 'label' => 'Download Chat', 'group' => 'Chat', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('nodes');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('organizations');
    }
};
