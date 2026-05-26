<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectStatusController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member', 'guest']): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), $roles), 403);
    }

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);
        return response()->json($project->statuses()->get());
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);

        $request->validate([
            'name'     => 'required|string|max:100',
            'color'    => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_done'  => 'boolean',
            'position' => 'nullable|integer|min:1',
        ]);

        $position = $request->position
            ?? ($project->statuses()->max('position') + 1);

        $status = ProjectStatus::create([
            'project_id' => $project->id,
            'name'       => $request->name,
            'color'      => $request->color ?? '#9CA3AF',
            'position'   => $position,
            'is_default' => false,
            'is_done'    => $request->boolean('is_done'),
            'slug'       => null,
        ]);

        return response()->json($status, 201);
    }

    public function update(Request $request, Workspace $workspace, Project $project, ProjectStatus $status): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($status->project_id !== $project->id, 404);

        $request->validate([
            'name'    => 'sometimes|required|string|max:100',
            'color'   => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_done' => 'sometimes|boolean',
        ]);

        $status->update($request->only(['name', 'color', 'is_done']));

        return response()->json($status->fresh());
    }

    public function destroy(Workspace $workspace, Project $project, ProjectStatus $status): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($status->project_id !== $project->id, 404);
        abort_if($status->is_default, 422, 'Cannot delete a default status.');

        // Move tasks in this status to the default (todo) status
        $default = $project->statuses()->where('slug', 'todo')->first()
            ?? $project->statuses()->orderBy('position')->first();

        if ($default) {
            DB::table('tasks')
                ->where('project_status_id', $status->id)
                ->update(['project_status_id' => $default->id, 'status' => 'todo']);
        }

        $status->delete();
        return response()->json(null, 204);
    }

    public function reorder(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);

        $request->validate([
            'statuses'            => 'required|array',
            'statuses.*.id'       => 'required|string',
            'statuses.*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $project) {
            foreach ($request->statuses as $item) {
                $project->statuses()
                    ->where('id', $item['id'])
                    ->update(['position' => $item['position']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
