<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Workspace;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BurndownController extends Controller
{
    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);
    }

    public function show(Workspace $workspace, Project $project, Sprint $sprint): JsonResponse
    {
        $this->gate($workspace);
        abort_if($sprint->project_id !== $project->id, 404);

        // All tasks in this sprint with estimates
        $tasks = $sprint->tasks()
            ->whereNotNull('estimate')
            ->with('projectStatus:id,is_done')
            ->get(['id', 'estimate', 'project_status_id']);

        $totalPoints = (int) $tasks->sum('estimate');

        if (!$sprint->start_date || !$sprint->end_date) {
            return response()->json([
                'sprint'       => [
                    'id'           => $sprint->id,
                    'name'         => $sprint->name,
                    'start_date'   => null,
                    'end_date'     => null,
                    'total_points' => $totalPoints,
                ],
                'daily' => [],
            ]);
        }

        $start   = $sprint->start_date->copy()->startOfDay();
        $end     = $sprint->end_date->copy()->endOfDay();
        $today   = now()->startOfDay();
        $plotEnd = $end->lt($today) ? $end : (Carbon::min($end, $today));

        // Get done dates per task from status history
        // Find the earliest date each task moved to a done status within the sprint range
        $taskIds = $tasks->pluck('id')->all();
        $doneDates = collect();

        if (!empty($taskIds)) {
            // Get done project_status ids for this project
            $doneStatusIds = DB::table('project_statuses')
                ->where('project_id', $project->id)
                ->where('is_done', true)
                ->pluck('id')
                ->all();

            if (!empty($doneStatusIds)) {
                $doneDates = DB::table('task_status_history')
                    ->whereIn('task_id', $taskIds)
                    ->whereIn('to_status_id', $doneStatusIds)
                    ->where('changed_at', '>=', $start)
                    ->orderBy('changed_at')
                    ->get(['task_id', 'changed_at'])
                    ->unique('task_id')
                    ->keyBy('task_id');
            }
        }

        // Build task estimate map
        $estimateMap = $tasks->keyBy('id')->map(fn($t) => (int) $t->estimate);

        // Generate daily data
        $period   = CarbonPeriod::create($start, $plotEnd);
        $totalDays = max(1, $start->diffInDays($end));
        $daily    = [];

        foreach ($period as $date) {
            $completedPoints = 0;
            foreach ($estimateMap as $taskId => $estimate) {
                $doneEntry = $doneDates->get($taskId);
                if ($doneEntry && Carbon::parse($doneEntry->changed_at)->lte($date->copy()->endOfDay())) {
                    $completedPoints += $estimate;
                }
            }

            $dayIndex    = $start->diffInDays($date);
            $idealPoints = round($totalPoints * (1 - $dayIndex / $totalDays));

            $daily[] = [
                'date'             => $date->toDateString(),
                'remaining_points' => max(0, $totalPoints - $completedPoints),
                'ideal_points'     => max(0, (int) $idealPoints),
            ];
        }

        return response()->json([
            'sprint' => [
                'id'           => $sprint->id,
                'name'         => $sprint->name,
                'start_date'   => $sprint->start_date->toDateString(),
                'end_date'     => $sprint->end_date->toDateString(),
                'total_points' => $totalPoints,
            ],
            'daily' => $daily,
        ]);
    }
}
