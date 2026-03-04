<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permissionKey): Response
    {
        $organizationId = $request->route('id');
        $user = $request->user();

        if (!$organizationId || !$user) {
            return response()->json(['message' => 'Unauthorized or missing organization context.'], 403);
        }

        // Retrieve all roles the user has in this organization
        $roles = $user->roles()->wherePivot('organization_id', $organizationId)->get();

        if ($roles->isEmpty()) {
            return response()->json(['message' => 'You are not a member of this organization.'], 403);
        }

        // Check if any of the roles have the specific permission required
        $hasPermission = false;
        foreach ($roles as $role) {
            if ($role->permissions()->where('key', $permissionKey)->exists()) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return response()->json(['message' => 'Forbidden. You do not have the required permission: ' . $permissionKey], 403);
        }

        return $next($request);
    }
}
