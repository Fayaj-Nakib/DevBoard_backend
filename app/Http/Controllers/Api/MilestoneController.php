<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MilestoneController extends Controller
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

        $milestones = $project->milestones()
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done')])
            ->orderBy('due_date')
            ->get();

        return response()->json($milestones);
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'nullable|date',
        ]);

        $milestone = Milestone::create([
            'project_id'  => $project->id,
            'created_by'  => $request->user()->id,
            'name'        => $request->name,
            'description' => $request->description,
            'due_date'    => $request->due_date,
        ]);

        return response()->json($milestone, 201);
    }

    public function show(Workspace $workspace, Project $project, Milestone $milestone): JsonResponse
    {
        $this->gate($workspace);
        abort_if($milestone->project_id !== $project->id, 404);

        return response()->json(
            $milestone->load(['tasks' => fn ($q) => $q->with('assignees')->orderBy('position')])
                       ->append('progress')
        );
    }

    public function update(Request $request, Workspace $workspace, Project $project, Milestone $milestone): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($milestone->project_id !== $project->id, 404);

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'nullable|date',
            'status'      => 'sometimes|in:open,closed',
        ]);

        $milestone->update($request->only('name', 'description', 'due_date', 'status'));

        return response()->json($milestone->append('progress'));
    }

    public function destroy(Workspace $workspace, Project $project, Milestone $milestone): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($milestone->project_id !== $project->id, 404);

        $milestone->delete();

        return response()->json(null, 204);
    }
}
