<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
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
        return 'task.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task' => [
                'id' => $this->task->id,
                'project_id' => $this->task->project_id,
                'title' => $this->task->title,
                'description' => $this->task->description,
                'status' => $this->task->status,
                'priority' => $this->task->priority,
                'position' => $this->task->position,
                'due_date' => $this->task->due_date,
                'started_at' => $this->task->started_at,
                'estimate' => $this->task->estimate,
                'milestone_id' => $this->task->milestone_id,
                'sprint_id' => $this->task->sprint_id,
                'project_status_id' => $this->task->project_status_id,
                'project_status' => $this->task->projectStatus,
                'assignees' => $this->task->assignees,
                'labels' => $this->task->labels,
            ],
        ];
    }
}
