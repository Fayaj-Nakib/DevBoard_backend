<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Task $task)
    {
        $this->task->loadMissing(['assignees', 'labels', 'projectStatus']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("private-project.{$this->task->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.created';
    }

    public function broadcastWith(): array
    {
        return [
            'task' => [
                'id' => $this->task->id,
                'project_id' => $this->task->project_id,
                'title' => $this->task->title,
                'status' => $this->task->status,
                'priority' => $this->task->priority,
                'position' => $this->task->position,
                'due_date' => $this->task->due_date,
                'estimate' => $this->task->estimate,
                'project_status_id' => $this->task->project_status_id,
                'project_status' => $this->task->projectStatus,
                'assignees' => $this->task->assignees,
                'labels' => $this->task->labels,
            ],
        ];
    }
}
