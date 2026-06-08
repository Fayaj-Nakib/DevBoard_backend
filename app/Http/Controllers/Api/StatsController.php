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
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_if(! in_array($workspace->userRole($user), ['owner', 'admin', 'member', 'guest']), 403);
    }

    public function projectStats(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $days = max(1, (int) ($request->query('days', 30)));
        $today = now()->toDateString();
        $base = $project->tasks()->whereNull('parent_id');

        $total = (int) $base->count();
        $closed = (int) $base->clone()->where('status', 'done')->count();
        $open = $total - $closed;
        $overdue = (int) $base->clone()
            ->whereDate('due_date', '<', $today)
            ->where('status', '!=', 'done')
            ->count();

        $completionRate = $total > 0 ? round($closed / $total * 100, 1) : 0;

        // Tasks by status (using project_statuses)
        $tasksByStatus = DB::table('tasks')
            ->join('project_statuses', 'tasks.project_status_id', '=', 'project_statuses.id')
            ->where('tasks.project_id', $project->id)
            ->whereNull('tasks.parent_id')
            ->groupBy('project_statuses.id', 'project_statuses.name', 'project_statuses.color', 'project_statuses.position')
            ->orderBy('project_statuses.position')
            ->select(
                'project_statuses.name as status_name',
                'project_statuses.color',
                DB::raw('count(*) as count')
            )
            ->get();

        // Tasks by assignee
        $tasksByAssignee = DB::table('task_assignees')
            ->join('tasks', 'task_assignees.task_id', '=', 'tasks.id')
            ->join('users', 'task_assignees.user_id', '=', 'users.id')
            ->where('tasks.project_id', $project->id)
            ->whereNull('tasks.parent_id')
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name', DB::raw('count(*) as count'))
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $since = Carbon::now()->subDays($days);
        $createdLast30 = (int) $base->clone()->where('created_at', '>=', $since)->count();
        $completedLast30 = (int) $base->clone()
            ->where('status', 'done')
            ->where('updated_at', '>=', $since)
            ->count();

        return response()->json([
            'open_tasks' => $open,
            'closed_tasks' => $closed,
            'overdue_tasks' => $overdue,
            'total_tasks' => $total,
            'completion_rate' => $completionRate,
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_assignee' => $tasksByAssignee,
            'tasks_created_last_30_days' => $createdLast30,
            'tasks_completed_last_30_days' => $completedLast30,
            'days' => $days,
        ]);
    }

    public function workspaceStats(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);

        $days = max(1, (int) ($request->query('days', 30)));
        $today = now()->toDateString();
        $projectIds = $workspace->projects()->pluck('id');
        $base = DB::table('tasks')->whereIn('project_id', $projectIds)->whereNull('parent_id');

        $total = (int) $base->clone()->count();
        $closed = (int) $base->clone()->where('status', 'done')->count();
        $open = $total - $closed;
        $overdue = (int) $base->clone()
            ->whereDate('due_date', '<', $today)
            ->where('status', '!=', 'done')
            ->count();

        $completionRate = $total > 0 ? round($closed / $total * 100, 1) : 0;
        $projectsCount = $projectIds->count();
        $activeSprintsCount = (int) DB::table('sprints')
            ->whereIn('project_id', $projectIds)
            ->where('status', 'active')
            ->count();

        $tasksByStatus = DB::table('tasks')
            ->join('project_statuses', 'tasks.project_status_id', '=', 'project_statuses.id')
            ->whereIn('tasks.project_id', $projectIds)
            ->whereNull('tasks.parent_id')
            ->groupBy('project_statuses.id', 'project_statuses.name', 'project_statuses.color', 'project_statuses.position')
            ->orderBy('project_statuses.position')
            ->select(
                'project_statuses.name as status_name',
                'project_statuses.color',
                DB::raw('count(*) as count')
            )
            ->get();

        $tasksByAssignee = DB::table('task_assignees')
            ->join('tasks', 'task_assignees.task_id', '=', 'tasks.id')
            ->join('users', 'task_assignees.user_id', '=', 'users.id')
            ->whereIn('tasks.project_id', $projectIds)
            ->whereNull('tasks.parent_id')
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name', DB::raw('count(*) as count'))
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $since = Carbon::now()->subDays($days);
        $createdLast30 = (int) $base->clone()->where('created_at', '>=', $since)->count();
        $completedLast30 = (int) $base->clone()
            ->where('status', 'done')
            ->where('updated_at', '>=', $since)
            ->count();

        return response()->json([
            'open_tasks' => $open,
            'closed_tasks' => $closed,
            'overdue_tasks' => $overdue,
            'total_tasks' => $total,
            'completion_rate' => $completionRate,
            'projects_count' => $projectsCount,
            'active_sprints_count' => $activeSprintsCount,
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_assignee' => $tasksByAssignee,
            'tasks_created_last_30_days' => $createdLast30,
            'tasks_completed_last_30_days' => $completedLast30,
            'days' => $days,
        ]);
    }

    public function velocity(Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $sprints = $project->sprints()
            ->where('status', 'completed')
            ->orderByDesc('end_date')
            ->limit(5)
            ->get(['id', 'name', 'start_date', 'end_date']);

        $data = $sprints->map(function ($sprint) {
            $planned = (int) $sprint->tasks()->whereNotNull('estimate')->sum('estimate');
            $completed = (int) $sprint->tasks()
                ->where('status', 'done')
                ->whereNotNull('estimate')
                ->sum('estimate');
            $rate = $planned > 0 ? round($completed / $planned * 100, 1) : 0;

            return [
                'sprint_id' => $sprint->id,
                'sprint_name' => $sprint->name,
                'planned_points' => $planned,
                'completed_points' => $completed,
                'completion_rate' => $rate,
            ];
        })->reverse()->values();

        $avgVelocity = $data->count() > 0
            ? round($data->avg('completed_points'), 1)
            : 0;

        return response()->json([
            'sprints' => $data,
            'average_velocity' => $avgVelocity,
        ]);
    }
}
