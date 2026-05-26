<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimelineController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);
    }

    public function index(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'milestone_id' => 'nullable|string|exists:milestones,id',
            'assignee_id'  => 'nullable|string|exists:users,id',
            'sprint_id'    => 'nullable|string|exists:sprints,id',
        ]);

        $query = $project->tasks()
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->whereNotNull('started_at')->orWhereNotNull('due_date');
            })
            ->with([
                'assignees:id,name',
                'milestone:id,name',
                'projectStatus:id,name,color,slug,is_done',
                'blockedBy:id',
            ]);

        if ($request->filled('milestone_id')) {
            $query->where('milestone_id', $request->milestone_id);
        }
        if ($request->filled('assignee_id')) {
            $query->whereHas('assignees', fn($q) => $q->where('users.id', $request->assignee_id));
        }
        if ($request->filled('sprint_id')) {
            $query->where('sprint_id', $request->sprint_id);
        }

        $tasks = $query->orderBy('started_at')->orderBy('due_date')->get();

        // Group by milestone
        $grouped = [];
        foreach ($tasks as $task) {
            $milestoneId = $task->milestone_id ?? '__none__';
            if (!isset($grouped[$milestoneId])) {
                $grouped[$milestoneId] = [
                    'milestone' => $task->milestone ? ['id' => $task->milestone->id, 'name' => $task->milestone->name] : null,
                    'tasks'     => [],
                ];
            }
            $grouped[$milestoneId]['tasks'][] = [
                'id'           => $task->id,
                'title'        => $task->title,
                'started_at'   => $task->started_at?->toDateString(),
                'due_date'     => $task->due_date?->toDateString(),
                'status'       => $task->status,
                'priority'     => $task->priority,
                'estimate'     => $task->estimate,
                'assignees'    => $task->assignees->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
                'project_status' => $task->projectStatus ? [
                    'id'    => $task->projectStatus->id,
                    'name'  => $task->projectStatus->name,
                    'color' => $task->projectStatus->color,
                ] : null,
                'blocked_by_ids' => $task->blockedBy->pluck('id'),
            ];
        }

        return response()->json(array_values($grouped));
    }
}
