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
    // Helper to deeply verify permissions per organization.
    private function hasPermission($user, $orgId, $permissionKey)
    {
        $roles = $user->roles()->wherePivot('organization_id', $orgId)->get();
        foreach ($roles as $role) {
            if ($role->permissions()->where('key', $permissionKey)->exists()) {
                return true;
            }
        }
        return false;
    }

    // Helper to get all descendant node IDs.
    private function getDescendantNodeIds($nodeIds, $orgId)
    {
        if (empty($nodeIds))
            return [];
        $ids = $nodeIds;
        $current = $nodeIds;
        while (!empty($current)) {
            $children = Node::where('organization_id', $orgId)->whereIn('parent_id', $current)->pluck('id')->toArray();
            $current = array_diff($children, $ids);
            $ids = array_merge($ids, $current);
        }
        return $ids;
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

        $userNodeIds = $user->roles()->wherePivot('organization_id', $organizationId)->pluck('role_user.node_id')->filter()->unique()->toArray();
        $isGlobal = $user->roles()->wherePivot('organization_id', $organizationId)->whereNull('role_user.node_id')->exists();

        $allowedNodeIds = $isGlobal ? null : $this->getDescendantNodeIds($userNodeIds, $organizationId);

        DB::beginTransaction();
        try {
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
                if (!$isGlobal && $t['target_type'] == 'node') {
                    if (!in_array($t['target_id'], $allowedNodeIds)) {
                        throw new \Exception("Cannot target node outside of your hierarchical scope.");
                    }
                }
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

        $reports = Report::where('organization_id', $organizationId)
            ->whereHas('targets', function ($q) use ($user, $userRoles, $userNodes) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('target_type', 'user')->where('target_id', $user->id);
                })->orWhere(function ($q2) use ($userRoles) {
                    $q2->where('target_type', 'role')->whereIn('target_id', $userRoles);
                })->orWhere(function ($q2) use ($userNodes) {
                    $q2->where('target_type', 'node')->whereIn('target_id', $userNodes);
                });
            })
            ->whereDoesntHave('submissions', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with('questions')
            ->get();

        return response()->json(['status' => 'success', 'reports' => $reports]);
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
