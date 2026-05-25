<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTaskAssignedNotification;
use App\Jobs\SendTaskWatcherNotification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    public function index(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $query = $project->tasks()
            ->with(['assignees', 'creator:id,name', 'labels']);

        // ── Filters ─────────────────────────────────────────────────────────
        if ($request->filled('label_ids')) {
            $ids = (array) $request->label_ids;
            $query->whereHas('labels', fn($q) => $q->whereIn('labels.id', $ids));
        }

        if ($request->filled('assignee_ids')) {
            $ids = (array) $request->assignee_ids;
            $query->whereHas('assignees', fn($q) => $q->whereIn('users.id', $ids));
        }

        if ($request->filled('milestone_id')) {
            $query->where('milestone_id', $request->milestone_id);
        }

        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }

        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('has_subtasks')) {
            $query->whereHas('children');
        }

        if ($request->boolean('is_overdue')) {
            $query->whereDate('due_date', '<', today())
                  ->where('status', '!=', 'done');
        }

        if ($request->filled('watcher_id')) {
            $query->whereHas('watchers', fn($q) => $q->where('users.id', $request->watcher_id));
        }

        // ── Sorting ──────────────────────────────────────────────────────────
        $allowedSorts = ['due_date', 'created_at', 'title', 'estimate'];
        $sortBy  = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : null;
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        if ($sortBy) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('status')->orderBy('position');
        }

        return response()->json($query->get()->groupBy('status'));
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'priority'       => 'in:low,medium,high',
            'due_date'       => 'nullable|date',
            'started_at'     => 'nullable|date',
            'milestone_id'   => 'nullable|string|exists:milestones,id',
            'sprint_id'      => 'nullable|string|exists:sprints,id',
            'estimate'       => 'nullable|integer|min:0|max:9999',
            'assignee_ids'   => 'nullable|array',
            'assignee_ids.*' => 'string|exists:users,id',
            'label_ids'      => 'nullable|array',
            'label_ids.*'    => 'string|exists:labels,id',
        ]);

        $position = $project->tasks()
            ->where('status', 'todo')
            ->max('position') + 1;

        $task = Task::create([
            'project_id'   => $project->id,
            'created_by'   => $request->user()->id,
            'title'        => $request->title,
            'description'  => $request->description,
            'priority'     => $request->priority ?? 'medium',
            'due_date'     => $request->due_date,
            'started_at'   => $request->started_at,
            'milestone_id' => $request->milestone_id,
            'sprint_id'    => $request->sprint_id,
            'estimate'     => $request->estimate,
            'position'     => $position,
            'status'       => 'todo',
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
        abort_if($task->project_id !== $project->id, 404);

        return response()->json(
            $task->load([
                'assignees', 'creator:id,name,email',
                'comments.user:id,name,email',
                'labels', 'attachments.uploader:id,name',
                'watchers', 'milestone:id,name',
                'sprint:id,name,status', 'children.assignees',
            ])
        );
    }

    public function update(Request $request, Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);
        abort_if($task->project_id !== $project->id, 404);

        $request->validate([
            'assignee_ids'   => 'sometimes|nullable|array',
            'assignee_ids.*' => 'string|exists:users,id',
            'label_ids'      => 'sometimes|nullable|array',
            'label_ids.*'    => 'string|exists:labels,id',
            'sprint_id'      => 'sometimes|nullable|string|exists:sprints,id',
            'estimate'       => 'sometimes|nullable|integer|min:0|max:9999',
        ]);

        $oldStatus = $task->status;
        $task->update($request->only([
            'title', 'description', 'status', 'priority', 'due_date', 'started_at',
            'milestone_id', 'sprint_id', 'estimate', 'position',
        ]));

        // Notify watchers when status changes
        if ($request->filled('status') && $request->status !== $oldStatus) {
            $actorId = $request->user()->id;
            foreach ($task->watchers as $watcher) {
                if ($watcher->id !== $actorId) {
                    SendTaskWatcherNotification::dispatch($task, $watcher, 'status_changed', [
                        'from' => $oldStatus,
                        'to'   => $task->status,
                    ]);
                }
            }
        }

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
        abort_if($task->project_id !== $project->id, 404);

        $task->delete();

        return response()->json(null, 204);
    }

    public function watch(Request $request, Task $task): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $workspace = $task->project->workspace;
        $this->gate($workspace);

        $task->watchers()->syncWithoutDetaching([$user->id]);

        return response()->json(['message' => 'Watching.']);
    }

    public function unwatch(Request $request, Task $task): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $workspace = $task->project->workspace;
        $this->gate($workspace);

        $task->watchers()->detach($user->id);

        return response()->json(null, 204);
    }

    public function indexSubtasks(Workspace $workspace, Project $project, Task $task): JsonResponse
    {
        $this->gate($workspace);
        abort_if($task->project_id !== $project->id, 404);

        return response()->json(
            $task->children()->with(['assignees', 'labels'])->get()
        );
    }

    public function storeSubtask(Request $request, Workspace $workspace, Project $project, Task $parent): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'priority'       => 'in:low,medium,high',
            'due_date'       => 'nullable|date',
            'assignee_ids'   => 'nullable|array',
            'assignee_ids.*' => 'string|exists:users,id',
        ]);

        $position = $parent->children()->max('position') + 1;

        $subtask = Task::create([
            'project_id'  => $project->id,
            'parent_id'   => $parent->id,
            'created_by'  => $request->user()->id,
            'title'       => $request->title,
            'description' => $request->description,
            'priority'    => $request->priority ?? 'medium',
            'due_date'    => $request->due_date,
            'position'    => $position,
            'status'      => 'todo',
        ]);

        if ($request->filled('assignee_ids')) {
            $subtask->assignees()->sync($request->assignee_ids);
            $currentUserId = $request->user()->id;
            foreach ($subtask->assignees as $assignee) {
                if ($assignee->id !== $currentUserId) {
                    SendTaskAssignedNotification::dispatch($subtask, $assignee);
                }
            }
        }

        return response()->json($subtask->load(['assignees', 'parent']), 201);
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
