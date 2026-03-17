<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Report;
use App\Models\ReportQuestion;
use App\Models\ReportTarget;
use App\Models\ReportSubmission;
use App\Models\ReportAnswer;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    use \App\Traits\RoleContextHelper;


    public function getTargets(Request $request, $organizationId)
    {
        $user = $request->user();
        if (!$this->hasPermission($user, $organizationId, 'report.ask')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Missing report.ask permission.'], 403);
        }

        $activeRole = $this->getActiveRoleContext($user, $organizationId, $request);
        if (!$activeRole) {
            return response()->json(['status' => 'error', 'message' => 'No active role context found in this organization.'], 403);
        }

        $userNodeIds = $activeRole->pivot->node_id ? [$activeRole->pivot->node_id] : [];
        $isGlobal = is_null($activeRole->pivot->node_id);

        if ($isGlobal) {
            $nodes = Node::where('organization_id', $organizationId)->get();
            $roles = \App\Models\Role::where('organization_id', $organizationId)->withCount('permissions')->with('permissions')->get();
            $members = Organization::find($organizationId)->members()->with('roles')->get();
        } else {
            $inclusiveNodeIds = $this->getDescendantNodeIds($userNodeIds, $organizationId, true);
            $strictNodeIds = $this->getDescendantNodeIds($userNodeIds, $organizationId, false);
            
            // Nodes: Show user's current node and everything downstream
            $nodes = Node::whereIn('id', $inclusiveNodeIds)->get();
            
            // Roles: Only roles that are actively assigned strictly below the user
            $allowedRoleIds = DB::table('role_user')
                ->where('organization_id', $organizationId)
                ->whereIn('node_id', $strictNodeIds)
                ->pluck('role_id')
                ->unique();
            $roles = \App\Models\Role::whereIn('id', $allowedRoleIds)->withCount('permissions')->with('permissions')->get();
            
            // Users: Only users that hold a role strictly below the user
            $allowedUserIds = DB::table('role_user')
                ->where('organization_id', $organizationId)
                ->whereIn('node_id', $strictNodeIds)
                ->pluck('user_id')
                ->unique();
            $members = \App\Models\User::whereIn('id', $allowedUserIds)->with('roles')->get();
        }

        return response()->json([
            'status' => 'success',
            'nodes' => $nodes,
            'roles' => $roles,
            'members' => $members
        ]);
    }

    public function store(Request $request, $organizationId)
    {
        $user = $request->user();
        if (!$this->hasPermission($user, $organizationId, 'report.ask')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Missing report.ask permission.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'deadline' => 'nullable|date',
            'questions' => 'required|array',
            'targets' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $activeRole = $this->getActiveRoleContext($user, $organizationId, $request);
        if (!$activeRole) {
            return response()->json(['status' => 'error', 'message' => 'No active role context found in this organization.'], 403);
        }

        $userNodeIds = $activeRole->pivot->node_id ? [$activeRole->pivot->node_id] : [];
        $isGlobal = is_null($activeRole->pivot->node_id);

        $inclusiveNodeIds = $isGlobal ? null : $this->getDescendantNodeIds($userNodeIds, $organizationId, true);
        $strictNodeIds = $isGlobal ? null : $this->getDescendantNodeIds($userNodeIds, $organizationId, false);

        DB::beginTransaction();
        try {
            foreach ($request->targets as $t) {
                if (!$isGlobal) {
                    if ($t['target_type'] == 'node' && !in_array($t['target_id'], $inclusiveNodeIds)) {
                        throw new \Exception("Cannot target node outside of your hierarchical scope.");
                    }
                    if ($t['target_type'] == 'user') {
                        $userNodes = DB::table('role_user')->where('user_id', $t['target_id'])->where('organization_id', $organizationId)->pluck('node_id')->toArray();
                        if (empty(array_intersect($userNodes, $strictNodeIds))) {
                            throw new \Exception("Cannot target a user outside of your strict downstream scope.");
                        }
                    }
                    if ($t['target_type'] == 'role') {
                        $roleExists = DB::table('role_user')->where('organization_id', $organizationId)->where('role_id', $t['target_id'])->whereIn('node_id', $strictNodeIds)->exists();
                        if (!$roleExists) {
                            throw new \Exception("Cannot target a role outside of your strict downstream scope.");
                        }
                    }
                }
            }

            $report = Report::create([
                'organization_id' => $organizationId,
                'creator_id' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'deadline' => $request->deadline,
            ]);

            foreach ($request->questions as $index => $q) {
                ReportQuestion::create([
                    'report_id' => $report->id,
                    'type' => $q['type'],
                    'title' => $q['title'],
                    'is_required' => $q['is_required'] ?? false,
                    'options' => $q['options'] ?? null,
                    'order_index' => $index,
                ]);
            }

            foreach ($request->targets as $t) {
                ReportTarget::create([
                    'report_id' => $report->id,
                    'target_type' => $t['target_type'],
                    'target_id' => $t['target_id'],
                ]);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'report' => $report], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function pending(Request $request, $organizationId)
    {
        $user = $request->user();
        if (!$this->hasPermission($user, $organizationId, 'report.send')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Missing report.send permission.'], 403);
        }

        $userRoles = $user->roles()->wherePivot('organization_id', $organizationId)->pluck('roles.id')->toArray();
        $userNodes = $user->roles()->wherePivot('organization_id', $organizationId)->pluck('role_user.node_id')->filter()->toArray();
        $userIsGlobal = $user->roles()->wherePivot('organization_id', $organizationId)->whereNull('role_user.node_id')->exists();

        $reports = Report::where('organization_id', $organizationId)
            ->whereHas('targets', function ($q) use ($user, $userRoles, $userNodes, $userIsGlobal) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('target_type', 'user')->where('target_id', $user->id);
                })->orWhere(function ($q2) use ($userRoles) {
                    $q2->where('target_type', 'role')->whereIn('target_id', $userRoles);
                });
                
                if ($userIsGlobal) {
                    $q->orWhere('target_type', 'node'); 
                } else if (!empty($userNodes)) {
                    $q->orWhere(function ($q2) use ($userNodes) {
                        $q2->where('target_type', 'node')->whereIn('target_id', $userNodes);
                    });
                }
            })
            ->whereDoesntHave('submissions', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['questions', 'creator.roles'])
            ->get();

        // Prevent Upward Role Leakage & verify downstream intersections
        $filteredReports = $reports->filter(function($report) use ($organizationId, $userNodes, $userIsGlobal) {
            $creator = $report->creator;
            if (!$creator) return true; // safety
            
            $creatorIsGlobal = $creator->roles()->wherePivot('organization_id', $organizationId)->whereNull('role_user.node_id')->exists();
            if ($creatorIsGlobal) return true;
            
            // If the viewer is completely global, they can see EVERYTHING.
            if ($userIsGlobal) return true;
            
            $creatorActiveRole = $creator->roles()->wherePivot('organization_id', $organizationId)->first();
            $creatorNodeIds = $creatorActiveRole && $creatorActiveRole->pivot->node_id ? [$creatorActiveRole->pivot->node_id] : [];
            $creatorInclusiveNodeIds = $this->getDescendantNodeIds($creatorNodeIds, $organizationId, true);
            
            // Check intersection. The report is only valid for this viewer if the viewer resides in at least one valid node reachable by the creator
            if (!empty(array_intersect($userNodes, $creatorInclusiveNodeIds))) {
                return true;
            }
            return false;
        })->values();

        return response()->json(['status' => 'success', 'reports' => $filteredReports]);
    }

    public function submit(Request $request, $organizationId, $reportId)
    {
        $user = $request->user();
        $report = Report::where('organization_id', $organizationId)->findOrFail($reportId);

        $validator = Validator::make($request->all(), [
            'answers' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $submission = ReportSubmission::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
            ]);

            foreach ($request->answers as $ans) {
                ReportAnswer::create([
                    'submission_id' => $submission->id,
                    'question_id' => $ans['question_id'],
                    'answer_data' => is_array($ans['value']) ? $ans['value'] : ['text' => $ans['value']],
                ]);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'submission' => $submission]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function createdReports(Request $request, $organizationId)
    {
        $user = $request->user();
        if (!$this->hasPermission($user, $organizationId, 'report.ask')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Missing report.ask permission.'], 403);
        }

        $reports = Report::where('organization_id', $organizationId)
            ->where('creator_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'reports' => $reports]);
    }

    public function submissions(Request $request, $organizationId, $reportId)
    {
        $user = $request->user();
        if (!$this->hasPermission($user, $organizationId, 'report.ask')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Missing report.ask permission.'], 403);
        }

        $report = Report::where('organization_id', $organizationId)
            ->where('creator_id', $user->id)
            ->findOrFail($reportId);

        $submissions = ReportSubmission::where('report_id', $report->id)
            ->with(['user:id,name,email,handle', 'answers.question'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'report' => $report->load('questions'),
            'submissions' => $submissions
        ]);
    }
}
