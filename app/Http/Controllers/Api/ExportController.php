<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member', 'guest']), 403);
    }

    public function export(Request $request, Workspace $workspace, Project $project): StreamedResponse|Response
    {
        $this->projectGate($workspace, $project);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $format = $request->query('format', 'json');
        abort_if(!in_array($format, ['json', 'csv']), 422);

        if ($format === 'json') {
            return $this->exportJson($project);
        }

        return $this->exportCsv($project);
    }

    private function exportJson(Project $project): StreamedResponse
    {
        $filename = 'project-' . $project->id . '-export.json';

        return response()->streamDownload(function () use ($project) {
            $out = fopen('php://output', 'w');

            fwrite($out, json_encode([
                'project'   => $project->only('id', 'name', 'description', 'status'),
                'statuses'  => $project->statuses()->get(['id', 'name', 'color', 'position', 'is_default', 'is_done', 'slug']),
                'milestones' => $project->milestones()->get(['id', 'name', 'description', 'due_date', 'status']),
                'sprints'   => $project->sprints()->get(['id', 'name', 'goal', 'start_date', 'end_date', 'status']),
                'tasks'     => $project->tasks()
                    ->with(['assignees:id,name,email', 'labels:id,name,color', 'comments.user:id,name', 'customFieldValues.definition'])
                    ->cursor()
                    ->map(fn($t) => array_merge($t->toArray(), [
                        'total_logged_minutes' => $t->total_logged_minutes,
                    ]))
                    ->values()
                    ->toArray(),
                'exported_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            fclose($out);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function exportCsv(Project $project): StreamedResponse
    {
        $filename = 'project-' . $project->id . '-export.csv';
        $fieldDefs = $project->customFieldDefinitions()->get()->keyBy('id');

        return response()->streamDownload(function () use ($project, $fieldDefs) {
            $out = fopen('php://output', 'w');

            // Build CSV headers
            $stdHeaders = ['id', 'title', 'description', 'status', 'priority', 'due_date', 'started_at', 'estimate', 'assignees', 'labels', 'milestone', 'sprint', 'total_logged_minutes'];
            $customHeaders = $fieldDefs->pluck('name')->toArray();
            fputcsv($out, array_merge($stdHeaders, $customHeaders));

            $project->tasks()
                ->with(['assignees:id,name', 'labels:id,name', 'milestone:id,name', 'sprint:id,name', 'customFieldValues'])
                ->cursor()
                ->each(function ($task) use ($out, $fieldDefs) {
                    $customValues = $task->customFieldValues->keyBy('field_definition_id');
                    $row = [
                        $task->id,
                        $task->title,
                        $task->description ?? '',
                        $task->status,
                        $task->priority,
                        $task->due_date,
                        $task->started_at,
                        $task->estimate ?? '',
                        $task->assignees->pluck('name')->implode(', '),
                        $task->labels->pluck('name')->implode(', '),
                        $task->milestone?->name ?? '',
                        $task->sprint?->name ?? '',
                        $task->total_logged_minutes,
                    ];
                    foreach ($fieldDefs->keys() as $defId) {
                        $row[] = $customValues[$defId]?->value ?? '';
                    }
                    fputcsv($out, $row);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
