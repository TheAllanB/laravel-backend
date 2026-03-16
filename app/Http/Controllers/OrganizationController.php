<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\CreateOrganizationRequest;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query();

        // Strict UI constraint: User can only search via exact 12-char UID
        if ($request->has('search') && strlen($request->search) === 12 && ctype_alnum($request->search)) {
            $query->where('uid', strtoupper($request->search));
        } else {
            // Return empty set if search is invalid or empty to hide other orgs
            return response()->json([
                'current_page' => 1,
                'data' => [],
                'first_page_url' => null,
                'last_page' => 1,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => null,
                'per_page' => 15,
                'prev_page_url' => null,
                'to' => 0,
                'total' => 0,
            ]);
        }

        // Exclude already joined orgs
        $userOrgs = $request->user()->organizations()->pluck('organizations.id');
        $query->whereNotIn('id', $userOrgs);

        return response()->json($query->paginate(15));
    }

    public function joinedOrganizations(Request $request)
    {
        return response()->json([
            'organizations' => $request->user()->organizations()->get()
        ]);
    }

    public function join(Request $request, $id)
    {
        $organization = Organization::findOrFail($id);
        $user = $request->user();

        if (!$user->organizations()->where('organization_id', $organization->id)->exists()) {
            // Check if request already exists
            $existingRequest = \App\Models\OrganizationRequest::where('user_id', $user->id)
                ->where('organization_id', $organization->id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json(['message' => 'Join request is already pending.'], 400);
            }

            \App\Models\OrganizationRequest::create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'status' => 'pending'
            ]);

            return response()->json(['message' => 'Join request sent successfully.'], 200);
        }

        return response()->json(['message' => 'Already a member.'], 400);
    }

    public function requests(Request $request, $id)
    {
        $organization = Organization::findOrFail($id);
        $requests = \App\Models\OrganizationRequest::where('organization_id', $id)
            ->where('status', 'pending')
            ->with('user:id,name,handle,email')
            ->get();
            
        return response()->json($requests);
    }

    public function acceptRequest(Request $request, $id, $requestId)
    {
        $organization = Organization::findOrFail($id);
        
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $joinRequest = \App\Models\OrganizationRequest::where('id', $requestId)
            ->where('organization_id', $id)
            ->firstOrFail();

        if ($joinRequest->status !== 'pending') {
            return response()->json(['message' => 'Request is no longer pending.'], 400);
        }

        DB::beginTransaction();
        try {
            $joinRequest->update(['status' => 'approved']);
            
            // Add user to organization
            $joinRequest->user->organizations()->attach($id);
            
            // Assign active role
            $joinRequest->user->roles()->attach($request->role_id, ['organization_id' => $id]);
            
            DB::commit();
            return response()->json(['message' => 'Request accepted safely']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Acceptance failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function rejectRequest(Request $request, $id, $requestId)
    {
        $joinRequest = \App\Models\OrganizationRequest::where('id', $requestId)
            ->where('organization_id', $id)
            ->firstOrFail();

        $joinRequest->update(['status' => 'rejected']);
        return response()->json(['message' => 'Request rejected cleanly']);
    }

    public function store(CreateOrganizationRequest $request)
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            $organization = Organization::create([
                'name' => $request->name,
                'uid' => $request->uid,
                'website' => $request->website,
                'location' => $request->location,
                'description' => $request->description,
                'created_by' => $user->id,
            ]);

            // Add owner to members
            $user->organizations()->attach($organization->id);

            // Create Owner role
            $ownerRole = Role::create([
                'organization_id' => $organization->id,
                'name' => 'Owner',
            ]);

            // Assign all permissions to Owner role
            $allPermissions = Permission::pluck('id');
            $ownerRole->permissions()->sync($allPermissions);

            // Assign owner role to creator
            $ownerRole->users()->attach($user->id, ['organization_id' => $organization->id]);

            // Handle custom roles if provided
            if ($request->has('roles')) {
                foreach ($request->roles as $roleData) {
                    $role = Role::create([
                        'organization_id' => $organization->id,
                        'name' => $roleData['name'],
                    ]);

                    if (isset($roleData['permissions'])) {
                        $permissionIds = Permission::whereIn('key', $roleData['permissions'])->pluck('id');
                        $role->permissions()->sync($permissionIds);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Organization created successfully',
                'organization' => $organization
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Org Creation Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create organization', 'error' => $e->getMessage()], 500);
        }
    }

    public function context(Request $request, $id)
    {
        $user = $request->user();
        $organization = $user->organizations()->where('organizations.id', $id)->first();

        if (!$organization) {
            return response()->json(['message' => 'Not a member of this organization'], 403);
        }

        $userRoles = $user->roles()->wherePivot('organization_id', $organization->id)->get();

        $requestedRoleId = $request->query('role_id');
        $activeRole = null;

        if ($requestedRoleId) {
            $activeRole = $userRoles->firstWhere('id', $requestedRoleId);
        }
        if (!$activeRole) {
            $activeRole = $userRoles->first();
        }

        return response()->json([
            'organization' => $organization,
            'role' => $activeRole ? ['id' => $activeRole->id, 'name' => $activeRole->name, 'node_id' => $activeRole->pivot->node_id ?? null] : null,
            'permissions' => $activeRole ? $activeRole->permissions()->pluck('key') : [],
            'all_roles' => $userRoles->map(function ($r) {
                return ['id' => $r->id, 'name' => $r->name, 'node_id' => $r->pivot->node_id ?? null];
            })->values()
        ]);
    }

    public function members(Request $request, $id)
    {
        $organization = Organization::findOrFail($id);

        // Eager load the roles specific to this organization to eliminate N+1 queries
        $members = $organization->members()->with(['roles' => function ($query) use ($id) {
            $query->wherePivot('organization_id', $id);
        }])->get()->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'handle' => $member->handle,
                'email' => $member->email,
                'roles' => $member->roles->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'name' => $r->name,
                        'node_id' => $r->pivot->node_id
                    ];
                })
            ];
        });

        return response()->json($members);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'website' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string'
        ]);

        $organization = Organization::findOrFail($id);
        $organization->update($request->only(['name', 'website', 'location', 'description']));

        return response()->json([
            'message' => 'Updated successfully',
            'organization' => $organization
        ]);
    }

    public function checkUid($uid)
    {
        if (strlen($uid) !== 12 || !ctype_alnum($uid)) {
            return response()->json(['available' => false], 400);
        }

        $exists = Organization::where('uid', strtoupper($uid))->exists();

        return response()->json([
            'available' => !$exists
        ]);
    }

    public function generateUid()
    {
        $uid = '';
        do {
            $uid = strtoupper(Str::random(12));
        } while (Organization::where('uid', $uid)->exists());

        return response()->json([
            'uid' => $uid
        ]);
    }
}
