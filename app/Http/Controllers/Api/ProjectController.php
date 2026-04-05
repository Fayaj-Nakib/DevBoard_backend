<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);
        return response()->json($workspace->projects()->withCount('tasks')->get());
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'created_by'   => $request->user()->id,
            'name'         => $request->name,
            'description'  => $request->description,
        ]);

        return response()->json($project, 201);
    }

    public function show(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);
        return response()->json(
            $project->load(['tasks' => fn($q) => $q->orderBy('position')])
        );
    }

    public function update(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        $request->validate(['name' => 'required|string|max:255']);
        $project->update($request->only('name', 'description', 'status'));
        return response()->json($project);
    }

    public function destroy(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        $project->delete();
        return response()->json(null, 204);
    }

    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        abort_if(!in_array($workspace->userRole(auth()->user()), $roles), 403);
    }
}
