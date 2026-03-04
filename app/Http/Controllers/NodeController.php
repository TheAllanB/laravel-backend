<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NodeController extends Controller
{
    /**
     * Get nodes for an organization, optionally filtered by parent_id.
     * If parent_id is missing, it fetches the root nodes (parent_id IS NULL).
     */
    public function index(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        // Optional: Check permissions here (e.g. org.view)

        $parentId = $request->query('parent_id');

        $query = $organization->nodes();

        if ($parentId === 'all') {
            // Fetch all nodes, no parent_id filter
        } elseif ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        $nodes = $query->orderBy('created_at', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'nodes' => $nodes
        ]);
    }

    /**
     * Create a new node in the organization.
     */
    public function store(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        // Determine which permission is needed based on parent_id
        $permissionKey = $request->filled('parent_id') ? 'node.sub.create' : 'node.main.create';

        $hasPermission = false;
        $userRoles = $request->user()->roles()->wherePivot('organization_id', $organizationId)->get();
        foreach ($userRoles as $role) {
            if ($role->permissions()->where('key', $permissionKey)->exists()) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. You do not have the required permission: ' . $permissionKey
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('nodes')->where(function ($query) use ($request, $organizationId) {
                    return $query->where('organization_id', $organizationId)
                        ->where('parent_id', $request->parent_id);
                })
            ],
            'type' => 'required|in:folder,chat',
            'parent_id' => 'nullable|exists:nodes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional validation to ensure parent_id actually belongs to this org
        if ($request->filled('parent_id')) {
            $parent = Node::where('organization_id', $organizationId)
                ->where('id', $request->parent_id)
                ->first();

            if (!$parent) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid parent_id for this organization.'
                ], 422);
            }
        }

        $node = new Node();
        $node->organization_id = $organizationId;
        $node->parent_id = $request->parent_id;
        $node->name = $request->name;
        $node->type = $request->type;
        $node->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Node created successfully',
            'node' => $node
        ], 201);
    }

    /**
     * Update an existing node (e.g., rename it).
     */
    public function update(Request $request, $organizationId, $nodeId)
    {
        $organization = Organization::findOrFail($organizationId);

        $node = Node::where('organization_id', $organizationId)->findOrFail($nodeId);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('nodes')->where(function ($query) use ($node, $organizationId) {
                    return $query->where('organization_id', $organizationId)
                        ->where('parent_id', $node->parent_id);
                })->ignore($node->id)
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $node->name = $request->name;
        $node->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Node updated successfully',
            'node' => $node
        ]);
    }
}
