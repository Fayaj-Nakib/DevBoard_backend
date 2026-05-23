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
        /** @var \App\Models\User $user */
        $user = auth()->user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $tasks = $project->tasks()
            ->with(['assignees', 'creator:id,name', 'labels'])
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
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'priority'     => 'in:low,medium,high',
            'due_date'     => 'nullable|date',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'string|exists:users,id',
            'label_ids'    => 'nullable|array',
            'label_ids.*'  => 'string|exists:labels,id',
        ]);

        $position = $project->tasks()
            ->where('status', 'todo')
            ->max('position') + 1;

        $task = Task::create([
            'project_id'  => $project->id,
            'created_by'  => $request->user()->id,
            'title'       => $request->title,
            'description' => $request->description,
            'priority'    => $request->priority ?? 'medium',
            'due_date'    => $request->due_date,
            'position'    => $position,
            'status'      => 'todo',
        ]);

        if ($request->filled('assignee_ids')) {
            $task->assignees()->sync($request->assignee_ids);
            $currentUserId = $request->user()->id;
            foreach ($task->assignees as $assignee) {
                if ($assignee->id !== $currentUserId) {
                    SendTaskAssignedNotification::dispatch($task, $assignee);
                }
            }
        }

        if ($request->filled('label_ids')) {
            $task->labels()->sync($request->label_ids);
        }

        return response()->json($task->load(['assignees', 'labels']), 201);
    }

    public function show(Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);

        return response()->json(
            $task->load(['assignees', 'creator', 'comments.user', 'labels'])
        );
    }

    public function update(Request $request, Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'assignee_ids'   => 'sometimes|nullable|array',
            'assignee_ids.*' => 'string|exists:users,id',
            'label_ids'      => 'sometimes|nullable|array',
            'label_ids.*'    => 'string|exists:labels,id',
        ]);

        $task->update($request->only([
            'title', 'description', 'status', 'priority', 'due_date', 'position',
        ]));

        if ($request->has('assignee_ids')) {
            $oldIds  = $task->assignees()->pluck('users.id')->all();
            $newIds  = $request->assignee_ids ?? [];
            $task->assignees()->sync($newIds);
            $addedIds    = array_diff($newIds, $oldIds);
            $currentUserId = $request->user()->id;
            if ($addedIds) {
                $task->load('assignees');
                foreach ($task->assignees->whereIn('id', $addedIds) as $assignee) {
                    if ($assignee->id !== $currentUserId) {
                        SendTaskAssignedNotification::dispatch($task, $assignee);
                    }
                }
            }
        }

        if ($request->has('label_ids')) {
            $task->labels()->sync($request->label_ids ?? []);
        }

        return response()->json($task->load(['assignees', 'labels']));
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
