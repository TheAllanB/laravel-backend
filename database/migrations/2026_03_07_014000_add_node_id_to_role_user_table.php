<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            // Drop the restrictive multi-role constraint (ignore if doesn't exist locally)
            try {
                $table->dropUnique('uk_role_user_user_org');
            } catch (\Exception $e) {
                // If it fails by name, try dropping by columns
                try {
                    $table->dropUnique(['user_id', 'organization_id']);
                } catch (\Exception $e2) {
                    // Silently continue if the constraint is already gone
                }
            }
            
            // Add the missing node_id column that controllers expect
            if (!Schema::hasColumn('role_user', 'node_id')) {
                $table->foreignId('node_id')->nullable()->constrained('nodes')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropForeign(['node_id']);
            $table->dropColumn('node_id');
            $table->unique(['user_id', 'organization_id'], 'uk_role_user_user_org');
        });
    }
};
