<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

if (!Schema::hasColumn('role_user', 'node_id')) {
    Schema::table('role_user', function (Blueprint $table) {
        $table->foreignId('node_id')->nullable()->constrained('nodes')->onDelete('cascade');
    });
    echo "Added node_id\n";
} else {
    echo "node_id already exists\n";
}
