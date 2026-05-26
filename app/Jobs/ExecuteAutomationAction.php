<?php

namespace App\Jobs;

use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Notification;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteAutomationAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 60;

    public function __construct(
        public AutomationRule $rule,
        public Task           $task,
        public ?string        $actorUserId = null,
    ) {}

    public function handle(): void
    {
        try {
            $this->executeAction();

            AutomationLog::create([
                'rule_id'      => $this->rule->id,
                'task_id'      => $this->task->id,
                'triggered_at' => now(),
                'result'       => 'success',
            ]);
        } catch (Throwable $e) {
            AutomationLog::create([
                'rule_id'       => $this->rule->id,
                'task_id'       => $this->task->id,
                'triggered_at'  => now(),
                'result'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function executeAction(): void
    {
        $config = $this->rule->action_config ?? [];

        match ($this->rule->action_type) {
            'change_status'     => $this->changeStatus($config),
            'assign_user'       => $this->assignUser($config),
            'add_label'         => $this->addLabel($config),
            'post_comment'      => $this->postComment($config),
            'send_notification' => $this->sendNotification($config),
            default             => null,
        };
    }

    private function changeStatus(array $config): void
    {
        $statusId = $config['status_id'] ?? null;
        if (!$statusId) return;

        $status = ProjectStatus::find($statusId);
        if (!$status || $status->project_id !== $this->task->project_id) return;

        $this->task->update([
            'project_status_id' => $statusId,
            'status'            => $status->slug ?? ($status->is_done ? 'done' : 'todo'),
        ]);
    }

    private function assignUser(array $config): void
    {
        $userId = $config['user_id'] ?? null;
        if (!$userId) return;

        $user = User::find($userId);
        if (!$user) return;

        $this->task->assignees()->syncWithoutDetaching([$userId]);
    }

    private function addLabel(array $config): void
    {
        $labelId = $config['label_id'] ?? null;
        if (!$labelId) return;

        $label = Label::find($labelId);
        if (!$label) return;

        $this->task->labels()->syncWithoutDetaching([$labelId]);
    }

    private function postComment(array $config): void
    {
        $body = $config['comment'] ?? null;
        if (!$body) return;

        // Post as the task creator or project owner
        $userId = $this->task->created_by;

        Comment::create([
            'task_id'    => $this->task->id,
            'user_id'    => $userId,
            'body'       => '[Automation] ' . $body,
        ]);
    }

    private function sendNotification(array $config): void
    {
        $userId  = $config['user_id'] ?? null;
        $message = $config['message'] ?? 'An automation rule was triggered on task: ' . $this->task->title;

        $recipients = $userId
            ? User::where('id', $userId)->get()
            : $this->task->assignees;

        foreach ($recipients as $user) {
            Notification::create([
                'user_id'    => $user->id,
                'type'       => 'TaskUpdated',
                'data'       => [
                    'task_id'    => $this->task->id,
                    'task_title' => $this->task->title,
                    'project'    => $this->task->project->name,
                    'action'     => 'automation_triggered',
                    'message'    => $message,
                ],
                'created_at' => now(),
            ]);
        }
    }
}
