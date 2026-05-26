<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $taskId,
        public readonly string $projectId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("private-project.{$this->projectId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id'    => $this->taskId,
            'project_id' => $this->projectId,
            'status'     => $this->status,
        ];
    }
}
