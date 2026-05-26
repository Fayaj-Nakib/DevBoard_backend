<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateAutomationRules;
use App\Models\Task;
use Illuminate\Console\Command;

class CheckDueDateAutomationsCommand extends Command
{
    protected $signature = 'automations:check-due-dates';

    protected $description = 'Evaluate due_date_reached automation triggers for tasks due today';

    public function handle(): int
    {
        $tasks = Task::query()
            ->whereDate('due_date', today())
            ->whereHas('project.automationRules', fn ($q) => $q
                ->where('is_active', true)
                ->where('trigger_type', 'due_date_reached')
            )
            ->with('project')
            ->get();

        $count = 0;
        foreach ($tasks as $task) {
            EvaluateAutomationRules::dispatch($task, 'due_date_reached');
            $count++;
        }

        $this->info("Queued due_date_reached evaluation for {$count} task(s).");

        return self::SUCCESS;
    }
}
