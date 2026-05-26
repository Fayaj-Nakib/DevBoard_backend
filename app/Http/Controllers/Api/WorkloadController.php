<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkloadController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_if(! in_array($workspace->userRole($user), ['owner', 'admin', 'member', 'guest']), 403);
    }

    public function index(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $request->validate([
            'sprint_id' => 'nullable|string|exists:sprints,id',
        ]);

        $query = $project->tasks()
            ->whereNull('parent_id')
            ->with('assignees:id,name,email');

        if ($request->filled('sprint_id')) {
            $query->where('sprint_id', $request->sprint_id);
        }

        $tasks = $query->get(['id', 'title', 'due_date', 'status', 'priority', 'estimate', 'project_status_id']);

        // Group by assignee
        $byAssignee = [];
        $today = now()->toDateString();

        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                if (! isset($byAssignee[$assignee->id])) {
                    $byAssignee[$assignee->id] = [
                        'user' => ['id' => $assignee->id, 'name' => $assignee->name, 'email' => $assignee->email],
                        'tasks' => [],
                        'total_tasks' => 0,
                        'overdue_count' => 0,
                        'total_estimate' => 0,
                    ];
                }

                $isOverdue = $task->due_date
                    && $task->due_date->toDateString() < $today
                    && $task->status !== 'done';

                $byAssignee[$assignee->id]['tasks'][] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date?->toDateString(),
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'estimate' => $task->estimate,
                    'is_overdue' => $isOverdue,
                ];

                $byAssignee[$assignee->id]['total_tasks']++;
                $byAssignee[$assignee->id]['total_estimate'] += (int) $task->estimate;
                if ($isOverdue) {
                    $byAssignee[$assignee->id]['overdue_count']++;
                }
            }
        }

        // Sort by total_tasks descending
        usort($byAssignee, fn ($a, $b) => $b['total_tasks'] - $a['total_tasks']);

        return response()->json(array_values($byAssignee));
    }
}
