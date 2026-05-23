<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    private function gate(Workspace $workspace, array $roles = ['owner', 'admin', 'member']): void
    {
        abort_if(!in_array($workspace->userRole(auth()->user()), $roles), 403);
    }

    public function index(Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);

        return response()->json(
            $workspace->labels()->orderBy('name')->get()
        );
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);

        $request->validate([
            'name'  => 'required|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $label = Label::create([
            'workspace_id' => $workspace->id,
            'created_by'   => $request->user()->id,
            'name'         => $request->name,
            'color'        => $request->color ?? '#6B7280',
        ]);

        return response()->json($label, 201);
    }

    public function update(Request $request, Workspace $workspace, Label $label): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($label->workspace_id !== $workspace->id, 404);

        $request->validate([
            'name'  => 'sometimes|string|max:50',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $label->update($request->only('name', 'color'));

        return response()->json($label);
    }

    public function destroy(Workspace $workspace, Label $label): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($label->workspace_id !== $workspace->id, 404);

        $label->delete();

        return response()->json(null, 204);
    }

    // Attach a label to a task
    public function attach(Request $request, Task $task, Label $label): JsonResponse
    {
        $workspace = $task->project->workspace;
        $this->gate($workspace);

        abort_if($label->workspace_id !== $workspace->id, 422, 'Label does not belong to this workspace.');

        $task->labels()->syncWithoutDetaching([$label->id]);

        return response()->json(['message' => 'Label attached.']);
    }

    // Detach a label from a task
    public function detach(Task $task, Label $label): JsonResponse
    {
        $workspace = $task->project->workspace;
        $this->gate($workspace);

        $task->labels()->detach($label->id);

        return response()->json(null, 204);
    }
}
