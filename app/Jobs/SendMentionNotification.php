<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMentionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Comment $comment, public User $mentioned) {}

    public function handle(): void
    {
        $task = $this->comment->task;

        Notification::create([
            'user_id' => $this->mentioned->id,
            'type' => 'Mentioned',
            'data' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'project' => $task->project->name,
                'comment_id' => $this->comment->id,
                'mentioned_by' => $this->comment->user->name,
            ],
            'created_at' => now(),
        ]);
    }
}
