<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Comment $comment)
    {
        $this->comment->loadMissing('user:id,name,email');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("private-project.{$this->comment->task->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }

    public function broadcastWith(): array
    {
        return [
            'comment' => [
                'id' => $this->comment->id,
                'task_id' => $this->comment->task_id,
                'body' => $this->comment->body,
                'user' => $this->comment->user,
                'created_at' => $this->comment->created_at,
            ],
        ];
    }
}
