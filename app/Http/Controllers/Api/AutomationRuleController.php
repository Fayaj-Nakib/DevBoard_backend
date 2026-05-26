<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutomationRuleController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $rules = $project->automationRules()
            ->with(['logs' => fn($q) => $q->latest('triggered_at')->limit(1)])
            ->orderBy('created_at')
            ->get()
            ->map(fn($rule) => $this->formatRule($rule));

        return response()->json($rules);
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'is_active'      => 'boolean',
            'trigger_type'   => 'required|in:task_created,status_changed,due_date_reached,assignee_added,comment_added',
            'trigger_config' => 'nullable|array',
            'action_type'    => 'required|in:change_status,assign_user,add_label,post_comment,send_notification',
            'action_config'  => 'nullable|array',
        ]);

        $rule = $project->automationRules()->create($data);

        return response()->json($this->formatRule($rule), 201);
    }

    public function show(Workspace $workspace, Project $project, AutomationRule $automationRule): JsonResponse
    {
        $this->projectGate($workspace, $project);
        abort_if($automationRule->project_id !== $project->id, 404);

        $logs = $automationRule->logs()->limit(20)->get()->map(fn($log) => [
            'id'            => $log->id,
            'task_id'       => $log->task_id,
            'triggered_at'  => $log->triggered_at?->toIso8601String(),
            'result'        => $log->result,
            'error_message' => $log->error_message,
        ]);

        return response()->json(array_merge($this->formatRule($automationRule), ['recent_logs' => $logs]));
    }

    public function update(Request $request, Workspace $workspace, Project $project, AutomationRule $automationRule): JsonResponse
    {
        $this->gate($workspace);
        abort_if($automationRule->project_id !== $project->id, 404);

        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'is_active'      => 'sometimes|boolean',
            'trigger_type'   => 'sometimes|in:task_created,status_changed,due_date_reached,assignee_added,comment_added',
            'trigger_config' => 'sometimes|nullable|array',
            'action_type'    => 'sometimes|in:change_status,assign_user,add_label,post_comment,send_notification',
            'action_config'  => 'sometimes|nullable|array',
        ]);

        $automationRule->update($data);

        return response()->json($this->formatRule($automationRule->fresh()));
    }

    public function destroy(Workspace $workspace, Project $project, AutomationRule $automationRule): JsonResponse
    {
        $this->gate($workspace);
        abort_if($automationRule->project_id !== $project->id, 404);

        $automationRule->delete();

        return response()->json(null, 204);
    }

    private function formatRule(AutomationRule $rule): array
    {
        $lastLog = $rule->logs->first();
        return [
            'id'              => $rule->id,
            'name'            => $rule->name,
            'is_active'       => $rule->is_active,
            'trigger_type'    => $rule->trigger_type,
            'trigger_config'  => $rule->trigger_config,
            'action_type'     => $rule->action_type,
            'action_config'   => $rule->action_config,
            'last_triggered'  => $lastLog?->triggered_at?->toIso8601String(),
            'last_result'     => $lastLog?->result,
            'created_at'      => $rule->created_at?->toIso8601String(),
        ];
    }
}
