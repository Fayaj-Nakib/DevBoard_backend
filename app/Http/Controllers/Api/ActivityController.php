<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member', 'guest']), 403);
    }

    public function forTask(Request $request, Task $task): JsonResponse
    {
        $this->taskGuestCheck($task);

        $logs = DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->where('activity_logs.subject_type', 'task')
            ->where('activity_logs.subject_id', $task->id)
            ->orderByDesc('activity_logs.created_at')
            ->paginate(30, [
                'activity_logs.id',
                'activity_logs.action',
                'activity_logs.payload',
                'activity_logs.created_at',
                'users.id as actor_id',
                'users.name as actor_name',
            ]);

        return response()->json($this->formatPage($logs));
    }

    public function forProject(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $request->validate([
            'actor_id'    => 'nullable|string|exists:users,id',
            'action'      => 'nullable|string|max:100',
        ]);

        $taskIds = $project->tasks()->pluck('id');

        $query = DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->where(function ($q) use ($project, $taskIds) {
                $q->where(function ($inner) use ($taskIds) {
                    $inner->where('activity_logs.subject_type', 'task')
                          ->whereIn('activity_logs.subject_id', $taskIds);
                })->orWhere(function ($inner) use ($project) {
                    $inner->where('activity_logs.subject_type', 'project')
                          ->where('activity_logs.subject_id', $project->id);
                });
            })
            ->orderByDesc('activity_logs.created_at');

        if ($request->filled('actor_id')) {
            $query->where('activity_logs.user_id', $request->actor_id);
        }
        if ($request->filled('action')) {
            $query->where('activity_logs.action', $request->action);
        }

        $logs = $query->paginate(30, [
            'activity_logs.id',
            'activity_logs.action',
            'activity_logs.payload',
            'activity_logs.subject_type',
            'activity_logs.subject_id',
            'activity_logs.created_at',
            'users.id as actor_id',
            'users.name as actor_name',
        ]);

        return response()->json($this->formatPage($logs));
    }

    public function forWorkspace(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'actor_id' => 'nullable|string|exists:users,id',
            'action'   => 'nullable|string|max:100',
        ]);

        $query = DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->where('activity_logs.workspace_id', $workspace->id)
            ->orderByDesc('activity_logs.created_at');

        if ($request->filled('actor_id')) {
            $query->where('activity_logs.user_id', $request->actor_id);
        }
        if ($request->filled('action')) {
            $query->where('activity_logs.action', $request->action);
        }

        $logs = $query->paginate(30, [
            'activity_logs.id',
            'activity_logs.action',
            'activity_logs.payload',
            'activity_logs.subject_type',
            'activity_logs.subject_id',
            'activity_logs.created_at',
            'users.id as actor_id',
            'users.name as actor_name',
        ]);

        return response()->json($this->formatPage($logs));
    }

    private function formatPage(mixed $page): array
    {
        $items = collect($page->items())->map(function ($row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true) : (array) ($row->payload ?? []);
            return [
                'id'           => $row->id,
                'action'       => $row->action,
                'subject_type' => $row->subject_type ?? null,
                'subject_id'   => $row->subject_id ?? null,
                'payload'      => $payload,
                'created_at'   => $row->created_at,
                'actor'        => $row->actor_id ? ['id' => $row->actor_id, 'name' => $row->actor_name] : null,
            ];
        });

        return [
            'data'         => $items,
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
        ];
    }
}
