<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->memberships()
            ->with('workspace')
            ->get()
            ->pluck('workspace');

        return response()->json($workspaces);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);

        $workspace = Workspace::create([
            'name'     => $request->name,
            'owner_id' => $request->user()->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id'      => $request->user()->id,
            'role'         => 'owner',
        ]);

        return response()->json($workspace->load('members'), 201);
    }

    public function show(Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);
        return response()->json($workspace->load(['members.user', 'projects']));
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        $request->validate(['name' => 'required|string|max:255']);
        $workspace->update(['name' => $request->name]);
        return response()->json($workspace);
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->gate($workspace, ['owner']);
        $workspace->delete();
        return response()->json(null, 204);
    }

    public function addMember(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role'  => 'required|in:admin,member',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => $request->role]
        );

        return response()->json(['message' => 'Member added.']);
    }

    public function removeMember(Workspace $workspace, User $user): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);

        WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(null, 204);
    }

    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        abort_if(!in_array($workspace->userRole(auth()->user()), $roles), 403, 'Forbidden.');
    }
}
