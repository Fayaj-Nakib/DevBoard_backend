<?php

namespace App\Http\Controllers\Api;

use App\Events\SprintUpdated as SprintUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $sprints = $project->sprints()
            ->withCount(['tasks', 'tasks as done_count' => fn($q) => $q->where('status', 'done')])
            ->get()
            ->map(function ($sprint) {
                $sprint->append(['progress', 'velocity']);
                return $sprint;
            });

        return response()->json($sprints);
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'goal'       => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $sprint = $project->sprints()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'status'     => 'planning',
        ]);

        return response()->json($sprint, 201);
    }

    public function show(Workspace $workspace, Project $project, Sprint $sprint): JsonResponse
    {
        $this->projectGate($workspace, $project);

        abort_if($sprint->project_id !== $project->id, 404);

        $sprint->load(['tasks.assignees', 'tasks.labels']);
        $sprint->append(['progress', 'velocity']);

        return response()->json($sprint);
    }

    public function update(Request $request, Workspace $workspace, Project $project, Sprint $sprint): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);

        abort_if($sprint->project_id !== $project->id, 404);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'goal'       => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'sometimes|in:planning,active,completed',
        ]);

        // Only one active sprint per project
        if (($data['status'] ?? null) === 'active') {
            $project->sprints()
                ->where('status', 'active')
                ->where('id', '!=', $sprint->id)
                ->update(['status' => 'completed']);
        }

        $sprint->update($data);
        $sprint->append(['progress', 'velocity']);
        broadcast(new SprintUpdatedEvent($sprint))->toOthers();

        return response()->json($sprint);
    }

    public function destroy(Workspace $workspace, Project $project, Sprint $sprint): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);

        abort_if($sprint->project_id !== $project->id, 404);

        // Move tasks back to backlog (no sprint)
        $sprint->tasks()->update(['sprint_id' => null]);
        $sprint->delete();

        return response()->json(null, 204);
    }

    public function addTask(Request $request, Workspace $workspace, Project $project, Sprint $sprint): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);

        abort_if($sprint->project_id !== $project->id, 404);

        $data = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'string|exists:tasks,id',
        ]);

        Task::whereIn('id', $data['task_ids'])
            ->where('project_id', $project->id)
            ->update(['sprint_id' => $sprint->id]);

        return response()->json(['message' => 'Tasks added to sprint.']);
    }

    public function removeTask(Workspace $workspace, Project $project, Sprint $sprint, Task $task): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);

        abort_if($sprint->project_id !== $project->id, 404);
        abort_if($task->sprint_id !== $sprint->id, 422, 'Task not in this sprint.');

        $task->update(['sprint_id' => null]);

        return response()->json(null, 204);
    }

    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member', 'guest']): void
    {
        $role = $workspace->userRole(request()->user());
        abort_if(!in_array($role, $roles), 403, 'Forbidden.');
    }
}
