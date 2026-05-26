<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeLogController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_if(! in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);
    }

    public function index(Request $request, Task $task): JsonResponse
    {
        $workspace = $task->project->workspace;
        $this->gate($workspace);

        $logs = $task->timeLogs()
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($log) => $this->formatLog($log));

        return response()->json([
            'logs' => $logs,
            'total_logged_minutes' => $task->total_logged_minutes,
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $workspace = $task->project->workspace;
        $this->gate($workspace);

        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'type' => 'required|in:timer,manual',
            'duration_minutes' => 'required_if:type,manual|nullable|integer|min:1|max:999999',
            'started_at' => 'required_if:type,manual|nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($request->type === 'timer') {
            // Enforce: only one running timer per user at a time
            $running = TaskTimeLog::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->exists();
            abort_if($running, 422, 'You already have a running timer. Stop it before starting a new one.');

            $log = TaskTimeLog::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => now(),
            ]);
        } else {
            // Manual entry
            $log = TaskTimeLog::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => $request->started_at,
                'ended_at' => $request->started_at, // manual entries are complete
                'duration_minutes' => $request->duration_minutes,
                'note' => $request->note,
            ]);
        }

        return response()->json($this->formatLog($log->load('user:id,name,email')), 201);
    }

    public function update(Request $request, TaskTimeLog $timeLog): JsonResponse
    {
        $workspace = $timeLog->task->project->workspace;
        $this->gate($workspace);

        /** @var User $user */
        $user = $request->user();
        abort_if($timeLog->user_id !== $user->id, 403);

        $request->validate([
            'note' => 'sometimes|nullable|string|max:1000',
            'duration_minutes' => 'sometimes|nullable|integer|min:1|max:999999',
        ]);

        if ($timeLog->isRunning()) {
            // Stop the timer
            $endedAt = now();
            $durationMinutes = (int) ceil($timeLog->started_at->diffInMinutes($endedAt));
            $timeLog->update([
                'ended_at' => $endedAt,
                'duration_minutes' => max(1, $durationMinutes),
                'note' => $request->note ?? $timeLog->note,
            ]);
        } else {
            // Edit a manual entry
            $timeLog->update($request->only('note', 'duration_minutes'));
        }

        return response()->json($this->formatLog($timeLog->fresh()->load('user:id,name,email')));
    }

    public function destroy(Request $request, TaskTimeLog $timeLog): JsonResponse
    {
        $workspace = $timeLog->task->project->workspace;
        $this->gate($workspace);

        /** @var User $user */
        $user = $request->user();
        abort_if($timeLog->user_id !== $user->id, 403);

        $timeLog->delete();

        return response()->json(null, 204);
    }

    public function projectLogs(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->gate($workspace);

        $request->validate([
            'user_id' => 'nullable|string|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = TaskTimeLog::query()
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
            ->whereNotNull('duration_minutes')
            ->with(['user:id,name,email', 'task:id,title,project_id']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        $logs = $query->latest('started_at')->paginate(50);
        $total = $query->sum('duration_minutes');

        return response()->json([
            'data' => $logs->items(),
            'total' => $logs->total(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'total_logged_minutes' => (int) $total,
        ]);
    }

    public function activeTimer(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $log = TaskTimeLog::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->with('task:id,title,project_id')
            ->first();

        if (! $log) {
            return response()->json(null);
        }

        return response()->json($this->formatLog($log));
    }

    private function formatLog(TaskTimeLog $log): array
    {
        return [
            'id' => $log->id,
            'task_id' => $log->task_id,
            'task_title' => $log->task?->title,
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'started_at' => $log->started_at?->toIso8601String(),
            'ended_at' => $log->ended_at?->toIso8601String(),
            'duration_minutes' => $log->duration_minutes,
            'note' => $log->note,
            'is_running' => $log->isRunning(),
        ];
    }
}
