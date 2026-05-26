<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomFieldController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);
    }

    // ── Definitions ───────────────────────────────────────────────────────────

    public function index(Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);
        abort_if($project->workspace_id !== $workspace->id, 404);

        return response()->json($project->customFieldDefinitions()->get());
    }

    public function store(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'field_type'  => 'required|in:text,number,date,select,url,checkbox',
            'options'     => 'nullable|array',
            'options.*'   => 'string',
            'is_required' => 'boolean',
        ]);

        $position = $project->customFieldDefinitions()->max('position') + 1;

        $field = CustomFieldDefinition::create(array_merge($data, [
            'project_id' => $project->id,
            'position'   => $position,
        ]));

        return response()->json($field, 201);
    }

    public function update(Request $request, Workspace $workspace, Project $project, CustomFieldDefinition $field): JsonResponse
    {
        $this->gate($workspace);
        abort_if($field->project_id !== $project->id, 404);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'options'     => 'nullable|array',
            'options.*'   => 'string',
            'is_required' => 'sometimes|boolean',
        ]);

        $field->update($data);

        return response()->json($field);
    }

    public function destroy(Workspace $workspace, Project $project, CustomFieldDefinition $field): JsonResponse
    {
        $this->gate($workspace);
        abort_if($field->project_id !== $project->id, 404);

        $field->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $request->validate([
            'fields'            => 'required|array',
            'fields.*.id'       => 'required|string|exists:custom_field_definitions,id',
            'fields.*.position' => 'required|integer',
        ]);

        foreach ($request->fields as $item) {
            CustomFieldDefinition::where('id', $item['id'])
                ->where('project_id', $project->id)
                ->update(['position' => $item['position']]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Values ────────────────────────────────────────────────────────────────

    public function upsertValues(Request $request, Task $task): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $workspace = $task->project->workspace;
        abort_if($workspace->userRole($user) === null, 403);

        // body: { field_definition_id => value, ... }
        $payload = $request->validate(['values' => 'required|array']);

        $definitions = CustomFieldDefinition::where('project_id', $task->project_id)
            ->whereIn('id', array_keys($payload['values']))
            ->get()
            ->keyBy('id');

        foreach ($payload['values'] as $defId => $rawValue) {
            $def = $definitions[$defId] ?? null;
            if (!$def) continue;

            // Type validation
            $value = $this->castValue($def->field_type, $rawValue);

            CustomFieldValue::updateOrCreate(
                ['field_definition_id' => $defId, 'task_id' => $task->id],
                ['value' => $value],
            );
        }

        return response()->json($this->taskFieldValues($task));
    }

    private function castValue(string $type, mixed $raw): ?string
    {
        if ($raw === null || $raw === '') return null;
        return match ($type) {
            'number'   => is_numeric($raw) ? (string) $raw : null,
            'checkbox' => $raw ? '1' : '0',
            default    => (string) $raw,
        };
    }

    private function taskFieldValues(Task $task): array
    {
        return $task->customFieldValues()
            ->with('definition')
            ->get()
            ->map(fn($v) => [
                'field_definition'    => $v->definition,
                'value'               => $v->value,
            ])
            ->toArray();
    }
}
