<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateRecurringTasksCommand extends Command
{
    protected $signature = 'recurring-tasks:generate';
    protected $description = 'Create next instances for all due recurring tasks';

    public function handle(): int
    {
        // Find original recurring tasks (not instances) whose current due date is today or past
        $tasks = Task::query()
            ->whereNotNull('recurrence_rule')
            ->whereNull('recurrence_parent_id')
            ->where(function ($q) {
                $q->whereNull('recurrence_ends_at')
                  ->orWhere('recurrence_ends_at', '>=', today());
            })
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->with(['assignees:id', 'labels:id', 'project:id,workspace_id'])
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            $nextDue = $this->nextDueDate($task->recurrence_rule, $task->due_date);
            if (!$nextDue) {
                continue;
            }

            // Stop generating if next occurrence is past the recurrence end
            if ($task->recurrence_ends_at && $nextDue->gt($task->recurrence_ends_at)) {
                continue;
            }

            $position = DB::table('tasks')
                ->where('project_id', $task->project_id)
                ->where('status', 'todo')
                ->max('position') + 1;

            // Create the instance for the current due date
            $instance = Task::create([
                'project_id'           => $task->project_id,
                'created_by'           => $task->created_by,
                'title'                => $task->title,
                'description'          => $task->description,
                'priority'             => $task->priority,
                'status'               => 'todo',
                'position'             => $position,
                'due_date'             => $task->due_date,
                'milestone_id'         => $task->milestone_id,
                'sprint_id'            => $task->sprint_id,
                'estimate'             => $task->estimate,
                'recurrence_parent_id' => $task->id,
            ]);

            $instance->assignees()->sync($task->assignees->pluck('id'));
            $instance->labels()->sync($task->labels->pluck('id'));

            // Advance the original task's due_date to the next occurrence
            $task->update(['due_date' => $nextDue]);

            DB::table('activity_logs')->insert([
                'id'           => (string) Str::uuid(),
                'workspace_id' => $task->project->workspace_id,
                'user_id'      => $task->created_by,
                'subject_type' => 'task',
                'subject_id'   => $instance->id,
                'action'       => 'recurring_task_created',
                'payload'      => json_encode([
                    'parent_task_id' => $task->id,
                    'parent_title'   => $task->title,
                    'due_date'       => $instance->due_date?->toDateString(),
                ]),
                'created_at'   => now(),
            ]);

            $count++;
        }

        $this->info("Created {$count} recurring task instance(s).");

        return self::SUCCESS;
    }

    private function nextDueDate(string $rule, mixed $currentDue): ?Carbon
    {
        $base = Carbon::parse($currentDue);

        return match ($rule) {
            'daily'   => $base->addDay(),
            'weekly'  => $base->addWeek(),
            'weekday' => $this->nextWeekday($base),
            'monthly' => $base->addMonth(),
            default   => null,
        };
    }

    private function nextWeekday(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay();
        while ($next->isWeekend()) {
            $next->addDay();
        }
        return $next;
    }
}
