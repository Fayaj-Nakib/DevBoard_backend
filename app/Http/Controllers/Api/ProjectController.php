<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);
        $user  = Auth::user();
        $query = $workspace->projects()->withCount('tasks');

        if ($workspace->userRole($user) === 'guest') {
            $memberProjectIds = ProjectMember::where('user_id', $user->id)->pluck('project_id');
            $query->whereIn('id', $memberProjectIds);
        }

        return response()->json($query->get());
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'created_by'   => $user->id,
            'name'         => $request->name,
            'description'  => $request->description,
        ]);

        $this->seedDefaultStatuses($project);

        // Auto-add creator as project manager
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'role'       => 'manager',
        ]);

        return response()->json($project, 201);
    }

    public function show(Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);
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

    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member', 'guest']): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    private function seedDefaultStatuses(Project $project): void
    {
        $defaults = [
            ['slug' => 'todo',        'name' => 'To Do',       'color' => '#9CA3AF', 'is_done' => false, 'is_default' => true,  'position' => 1],
            ['slug' => 'in_progress', 'name' => 'In Progress', 'color' => '#3B82F6', 'is_done' => false, 'is_default' => false, 'position' => 2],
            ['slug' => 'in_review',   'name' => 'In Review',   'color' => '#8B5CF6', 'is_done' => false, 'is_default' => false, 'position' => 3],
            ['slug' => 'done',        'name' => 'Done',        'color' => '#10B981', 'is_done' => true,  'is_default' => false, 'position' => 4],
        ];

        foreach ($defaults as $d) {
            ProjectStatus::create([
                'project_id' => $project->id,
                'name'       => $d['name'],
                'color'      => $d['color'],
                'position'   => $d['position'],
                'is_default' => $d['is_default'],
                'is_done'    => $d['is_done'],
                'slug'       => $d['slug'],
            ]);
        }
    }
}
