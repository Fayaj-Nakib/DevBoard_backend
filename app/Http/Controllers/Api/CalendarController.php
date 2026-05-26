<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_if(! in_array($workspace->userRole($user), ['owner', 'admin', 'member', 'guest']), 403);
    }

    public function index(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $request->validate([
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'assignee_id' => 'nullable|string|exists:users,id',
        ]);

        $monthStr = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($monthStr.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = $project->tasks()
            ->whereNull('parent_id')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('due_date', [$start, $end])
                    ->orWhereBetween('started_at', [$start, $end]);
            })
            ->with(['assignees:id,name', 'labels:id,name,color', 'projectStatus:id,name,color,slug']);

        if ($request->filled('assignee_id')) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $request->assignee_id));
        }

        $tasks = $query->get()->map(fn ($task) => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toDateString(),
            'started_at' => $task->started_at?->toDateString(),
            'estimate' => $task->estimate,
            'assignees' => $task->assignees->map(fn ($a) => ['id' => $a->id, 'name' => $a->name]),
            'labels' => $task->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color]),
            'project_status' => $task->projectStatus ? ['id' => $task->projectStatus->id, 'color' => $task->projectStatus->color] : null,
        ]);

        return response()->json([
            'month' => $monthStr,
            'tasks' => $tasks,
        ]);
    }
}
