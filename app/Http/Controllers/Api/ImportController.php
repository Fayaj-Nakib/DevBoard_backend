<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportProjectJob;
use App\Models\ImportJob;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_if(! in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);
    }

    public function import(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $request->validate([
            'file' => 'required|file|mimes:json,csv,txt|max:10240',
            'format' => 'required|in:json,csv',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $path = $request->file('file')->store('imports');

        $importJob = ImportJob::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'format' => $request->format,
        ]);

        ImportProjectJob::dispatch($importJob->id, $path, $request->format);

        return response()->json(['job_id' => $importJob->id], 202);
    }

    public function status(ImportJob $importJob): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Only the job owner or a workspace member can poll status
        $workspace = $importJob->project->workspace;
        abort_if($workspace->userRole($user) === null, 403);

        return response()->json([
            'id' => $importJob->id,
            'status' => $importJob->status,
            'format' => $importJob->format,
            'tasks_created' => $importJob->tasks_created,
            'error_message' => $importJob->error_message,
            'created_at' => $importJob->created_at,
            'updated_at' => $importJob->updated_at,
        ]);
    }
}
