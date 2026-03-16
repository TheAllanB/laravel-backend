<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Organizations
    Route::get('/me/organizations', [OrganizationController::class, 'joinedOrganizations']);
    Route::get('/organizations', [OrganizationController::class, 'index']); // Join screen listing
    Route::post('/organizations', [OrganizationController::class, 'store']); // Create Org
    Route::post('/organizations/{id}/join', [OrganizationController::class, 'join']); // Join Org

    // Organization Workspace
    Route::get('/organizations/{id}/context', [OrganizationController::class, 'context']);
    Route::get('/organizations/{id}/members', [OrganizationController::class, 'members']);
    // Update protected by middleware
    Route::patch('/organizations/{id}', [OrganizationController::class, 'update'])
        ->middleware('permission:org.profile.edit');

    // Join Requests
    Route::get('/organizations/{id}/requests', [OrganizationController::class, 'requests'])
        ->middleware('permission:org.members.edit');
    Route::post('/organizations/{id}/requests/{requestId}/accept', [OrganizationController::class, 'acceptRequest'])
        ->middleware('permission:org.members.edit');
    Route::post('/organizations/{id}/requests/{requestId}/reject', [OrganizationController::class, 'rejectRequest'])
        ->middleware('permission:org.members.edit');

    // Roles & Permissions
    Route::get('/permissions', [\App\Http\Controllers\RoleController::class, 'permissions']);
    Route::get('/organizations/{id}/roles', [\App\Http\Controllers\RoleController::class, 'index']);
    Route::post('/organizations/{id}/roles', [\App\Http\Controllers\RoleController::class, 'store'])
        ->middleware('permission:org.roles.create');
    Route::put('/organizations/{id}/roles/{roleId}', [\App\Http\Controllers\RoleController::class, 'update'])
        ->middleware('permission:org.roles.edit');
    Route::delete('/organizations/{id}/roles/{roleId}', [\App\Http\Controllers\RoleController::class, 'destroy'])
        ->middleware('permission:org.roles.edit');
    Route::put('/organizations/{id}/members/{userId}/roles', [\App\Http\Controllers\RoleController::class, 'assignRoles'])
        ->middleware('permission:org.members.edit');

    // Nodes (Permission logic handled within NodeController for store due to main vs sub nodes)
    Route::get('/organizations/{id}/nodes', [\App\Http\Controllers\NodeController::class, 'index']);
    Route::post('/organizations/{id}/nodes', [\App\Http\Controllers\NodeController::class, 'store']);
    Route::put('/organizations/{id}/nodes/{nodeId}', [\App\Http\Controllers\NodeController::class, 'update'])
        ->middleware('permission:nodes.name.edit');

    // Reports (Permissions checked within controller)
    Route::get('/organizations/{id}/reports/created', [\App\Http\Controllers\ReportController::class, 'createdReports']);
    Route::get('/organizations/{id}/reports/pending', [\App\Http\Controllers\ReportController::class, 'pending']);
    Route::get('/organizations/{id}/reports/{reportId}/submissions', [\App\Http\Controllers\ReportController::class, 'submissions']);
    Route::post('/organizations/{id}/reports', [\App\Http\Controllers\ReportController::class, 'store']);
    Route::post('/organizations/{id}/reports/{reportId}/submit', [\App\Http\Controllers\ReportController::class, 'submit']);
});

Route::get('/organizations/check-uid/{uid}', [OrganizationController::class, 'checkUid']);
Route::get('/organizations/generate-uid', [OrganizationController::class, 'generateUid']);
