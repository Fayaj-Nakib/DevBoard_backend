<?php

namespace App\Jobs;

use App\Models\AutomationRule;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateAutomationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Task $task,
        public string $triggerType,
        public array $triggerContext = [], // e.g. from_status_id, to_status_id, user_id
        public ?string $actorUserId = null,
    ) {}

    public function handle(): void
    {
        $rules = AutomationRule::query()
            ->where('project_id', $this->task->project_id)
            ->where('is_active', true)
            ->where('trigger_type', $this->triggerType)
            ->get();

        foreach ($rules as $rule) {
            if ($this->matchesTrigger($rule)) {
                ExecuteAutomationAction::dispatch($rule, $this->task, $this->actorUserId);
            }
        }
    }

    private function matchesTrigger(AutomationRule $rule): bool
    {
        $config = $rule->trigger_config ?? [];

        return match ($this->triggerType) {
            'task_created' => true,
            'comment_added' => true,
            'assignee_added' => empty($config['user_id'])
                || ($this->triggerContext['user_id'] ?? null) === $config['user_id'],
            'status_changed' => $this->matchesStatusTrigger($config),
            'due_date_reached' => true,
            default => false,
        };
    }

    private function matchesStatusTrigger(array $config): bool
    {
        $fromId = $this->triggerContext['from_status_id'] ?? null;
        $toId = $this->triggerContext['to_status_id'] ?? null;

        if (! empty($config['from_status_id']) && $config['from_status_id'] !== $fromId) {
            return false;
        }
        if (! empty($config['to_status_id']) && $config['to_status_id'] !== $toId) {
            return false;
        }

        return true;
    }
}
