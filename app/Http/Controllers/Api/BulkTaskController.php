<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkTaskController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);
    }

    public function __invoke(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'task_ids'        => 'required|array|min:1',
            'task_ids.*'      => 'required|string|exists:tasks,id',
            'action'          => 'required|in:move_status,assign,add_label,remove_label,set_milestone,delete',
            'payload'         => 'sometimes|array',
            'payload.status'       => 'required_if:action,move_status|in:todo,in_progress,in_review,done',
            'payload.user_id'      => 'required_if:action,assign|string|exists:users,id',
            'payload.label_id'     => 'required_if:action,add_label|required_if:action,remove_label|string|exists:labels,id',
            'payload.milestone_id' => 'nullable|string|exists:milestones,id',
        ]);

        // Scope to this project for workspace safety
        $tasks = Task::whereIn('id', $request->task_ids)
            ->where('project_id', $project->id)
            ->get();

        $updated = 0;
        $failed  = 0;
        $actor   = $request->user();
        $payload = $request->payload ?? [];

        foreach ($tasks as $task) {
            try {
                $this->applyAction($task, $request->action, $payload, $actor, $workspace);
                $updated++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return response()->json(compact('updated', 'failed'));
    }

    private function applyAction(Task $task, string $action, array $payload, $actor, Workspace $workspace): void
    {
        match ($action) {
            'move_status'   => $task->update(['status' => $payload['status']]),
            'assign'        => $task->assignees()->syncWithoutDetaching([$payload['user_id']]),
            'add_label'     => $task->labels()->syncWithoutDetaching([$payload['label_id']]),
            'remove_label'  => $task->labels()->detach($payload['label_id']),
            'set_milestone' => $task->update(['milestone_id' => $payload['milestone_id'] ?? null]),
            'delete'        => $task->delete(),
        };

        if ($action !== 'delete') {
            DB::table('activity_logs')->insert([
                'id'           => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'user_id'      => $actor->id,
                'subject_type' => 'task',
                'subject_id'   => $task->id,
                'action'       => 'bulk_' . $action,
                'payload'      => json_encode($payload),
                'created_at'   => now(),
            ]);
        }
    }
}
