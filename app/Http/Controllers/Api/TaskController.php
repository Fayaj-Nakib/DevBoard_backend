<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTaskAssignedNotification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        abort_if(!in_array($workspace->userRole(auth()->user()), $roles), 403);
    }

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $tasks = $project->tasks()
            ->with(['assignee:id,name,email', 'creator:id,name'])
            ->orderBy('status')
            ->orderBy('position')
            ->get()
            ->groupBy('status');

        return response()->json($tasks);
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'in:low,medium,high',
            'due_date'    => 'nullable|date',
            'assignee_id' => 'nullable|string|exists:users,id',
        ]);

        $position = $project->tasks()
            ->where('status', 'todo')
            ->max('position') + 1;

        $task = Task::create([
            'project_id'  => $project->id,
            'created_by'  => $request->user()->id,
            'assignee_id' => $request->assignee_id,
            'title'       => $request->title,
            'description' => $request->description,
            'priority'    => $request->priority ?? 'medium',
            'due_date'    => $request->due_date,
            'position'    => $position,
            'status'      => 'todo',
        ]);

        if ($task->assignee_id && $task->assignee_id !== $request->user()->id) {
            SendTaskAssignedNotification::dispatch($task);
        }

        return response()->json($task->load('assignee'), 201);
    }

    public function show(Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);

        return response()->json(
            $task->load(['assignee', 'creator', 'comments.user'])
        );
    }

    public function update(Request $request, Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);

        $oldAssignee = $task->assignee_id;

        $task->update($request->only([
            'title', 'description', 'status', 'priority',
            'due_date', 'assignee_id', 'position',
        ]));

        if ($request->assignee_id && $request->assignee_id !== $oldAssignee) {
            SendTaskAssignedNotification::dispatch($task->fresh());
        }

        return response()->json($task->load('assignee'));
    }

    public function destroy(Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);

        $task->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'tasks'            => 'required|array',
            'tasks.*.id'       => 'required|string',
            'tasks.*.status'   => 'required|in:todo,in_progress,in_review,done',
            'tasks.*.position' => 'required|integer',
        ]);

        DB::transaction(function () use ($request, $project) {
            foreach ($request->tasks as $item) {
                $project->tasks()
                    ->where('id', $item['id'])
                    ->update([
                        'status'   => $item['status'],
                        'position' => $item['position'],
                    ]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
