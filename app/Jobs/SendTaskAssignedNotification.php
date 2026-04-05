<?php

namespace App\Jobs;

use App\Mail\TaskAssignedMail;
use App\Models\Notification;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTaskAssignedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 60;

    public function __construct(public Task $task) {}

    public function handle(): void
    {
        $assignee = $this->task->assignee;
        if (!$assignee) return;

        Mail::to($assignee->email)->send(new TaskAssignedMail($this->task));

        Notification::create([
            'user_id'    => $assignee->id,
            'type'       => 'TaskAssigned',
            'data'       => [
                'task_id'     => $this->task->id,
                'task_title'  => $this->task->title,
                'project'     => $this->task->project->name,
                'assigned_by' => $this->task->creator->name,
            ],
            'created_at' => now(),
        ]);
    }
}
