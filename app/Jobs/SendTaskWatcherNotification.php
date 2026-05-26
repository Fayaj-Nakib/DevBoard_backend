<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTaskWatcherNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 60;

    public function __construct(
        public Task   $task,
        public User   $watcher,
        public string $action,   // e.g. 'status_changed', 'comment_added'
        public array  $meta = [],
    ) {}

    public function handle(): void
    {
        $notification = Notification::create([
            'user_id'    => $this->watcher->id,
            'type'       => 'TaskUpdated',
            'data'       => array_merge([
                'task_id'    => $this->task->id,
                'task_title' => $this->task->title,
                'project'    => $this->task->project->name,
                'action'     => $this->action,
            ], $this->meta),
            'created_at' => now(),
        ]);
        broadcast(new NotificationCreated($notification));
    }
}
