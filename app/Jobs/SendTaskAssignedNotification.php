<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Mail\TaskAssignedMail;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTaskAssignedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Task $task, public User $assignee) {}

    public function handle(): void
    {
        $this->task->loadMissing(['creator', 'project']);
        Mail::to($this->assignee->email)->send(new TaskAssignedMail($this->task, $this->assignee));

        $notification = Notification::create([
            'user_id' => $this->assignee->id,
            'type' => 'TaskAssigned',
            'data' => [
                'task_id' => $this->task->id,
                'task_title' => $this->task->title,
                'project' => $this->task->project->name,
                'assigned_by' => $this->task->creator->name,
            ],
            'created_at' => now(),
        ]);
        broadcast(new NotificationCreated($notification));
    }
}
