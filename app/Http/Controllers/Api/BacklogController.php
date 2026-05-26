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

class BacklogController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $tasks = $project->tasks()
            ->where('is_backlog', true)
            ->whereNull('parent_id')
            ->with(['assignees', 'labels', 'milestone:id,name', 'sprint:id,name'])
            ->orderBy('backlog_position')
            ->orderBy('created_at')
            ->get();

        return response()->json($tasks);
    }

    public function toggle(Request $request, Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);
        abort_if($task->project_id !== $project->id, 404);

        $task->update(['is_backlog' => !$task->is_backlog]);

        if ($task->is_backlog && $task->backlog_position === null) {
            $max = $project->tasks()->where('is_backlog', true)->max('backlog_position') ?? 0;
            $task->update(['backlog_position' => $max + 1]);
        }

        return response()->json($task->fresh());
    }

    public function reorder(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'tasks'            => 'required|array',
            'tasks.*.id'       => 'required|string',
            'tasks.*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $project) {
            foreach ($request->tasks as $item) {
                $project->tasks()
                    ->where('id', $item['id'])
                    ->update(['backlog_position' => $item['position']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
