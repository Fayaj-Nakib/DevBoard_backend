<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskTemplate;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskTemplateController extends Controller
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
        return response()->json($project->taskTemplates()->get());
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);

        $request->validate([
            'name'          => 'required|string|max:255',
            'default_title' => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'label_ids'     => 'nullable|array',
            'label_ids.*'   => 'string|exists:labels,id',
            'estimate'      => 'nullable|integer|min:0|max:9999',
            'checklist'     => 'nullable|array',
            'checklist.*.title' => 'required|string|max:255',
            'priority'      => 'in:low,medium,high',
        ]);

        $template = TaskTemplate::create([
            'project_id'    => $project->id,
            'created_by'    => $request->user()->id,
            'name'          => $request->name,
            'default_title' => $request->default_title,
            'description'   => $request->description,
            'label_ids'     => $request->label_ids,
            'estimate'      => $request->estimate,
            'checklist'     => $request->checklist,
            'priority'      => $request->priority ?? 'medium',
        ]);

        return response()->json($template, 201);
    }

    public function update(Request $request, Workspace $workspace, Project $project, TaskTemplate $template): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin', 'member']);
        abort_if($template->project_id !== $project->id, 404);

        $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'default_title' => 'sometimes|nullable|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'label_ids'     => 'sometimes|nullable|array',
            'label_ids.*'   => 'string|exists:labels,id',
            'estimate'      => 'sometimes|nullable|integer|min:0|max:9999',
            'checklist'     => 'sometimes|nullable|array',
            'checklist.*.title' => 'required_with:checklist|string|max:255',
            'priority'      => 'sometimes|in:low,medium,high',
        ]);

        $template->update($request->only([
            'name', 'default_title', 'description', 'label_ids', 'estimate', 'checklist', 'priority',
        ]));

        return response()->json($template->fresh());
    }

    public function destroy(Workspace $workspace, Project $project, TaskTemplate $template): JsonResponse
    {
        $this->gate($workspace, ['owner', 'admin']);
        abort_if($template->project_id !== $project->id, 404);

        $template->delete();
        return response()->json(null, 204);
    }
}
