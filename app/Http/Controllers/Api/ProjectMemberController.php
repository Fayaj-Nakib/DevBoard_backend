<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectMemberController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    private function requireManagerOrAdmin(Workspace $workspace, Project $project): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $wsRole = $workspace->userRole($user);

        // Workspace owner/admin bypass project role checks
        if (in_array($wsRole, ['owner', 'admin'])) return;

        $projectRole = $project->userRole($user);
        abort_if($projectRole !== 'manager', 403);
    }

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $members = $project->projectMembers()
            ->with('user:id,name,email')
            ->get()
            ->map(fn($m) => [
                'user'       => $m->user,
                'role'       => $m->role,
                'created_at' => $m->created_at,
            ]);

        return response()->json($members);
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->requireManagerOrAdmin($workspace, $project);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $data = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'role'    => 'required|in:viewer,editor,manager',
        ]);

        // User must already be a workspace member
        abort_if($workspace->userRole(User::find($data['user_id'])) === null, 422);

        $member = ProjectMember::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $data['user_id']],
            ['role' => $data['role']],
        );

        return response()->json([
            'user' => User::select('id', 'name', 'email')->find($data['user_id']),
            'role' => $member->role,
        ], 201);
    }

    public function update(Request $request, Workspace $workspace, Project $project, User $user): JsonResponse
    {
        $this->requireManagerOrAdmin($workspace, $project);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $data = $request->validate(['role' => 'required|in:viewer,editor,manager']);

        $member = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->update(['role' => $data['role']]);

        return response()->json(['user' => $user->only('id', 'name', 'email'), 'role' => $member->role]);
    }

    public function destroy(Workspace $workspace, Project $project, User $user): JsonResponse
    {
        $this->requireManagerOrAdmin($workspace, $project);
        abort_if($project->workspace_id !== $workspace->id, 404);

        ProjectMember::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(null, 204);
    }
}
