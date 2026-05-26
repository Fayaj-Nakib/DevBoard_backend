<?php

namespace App\Events;

use App\Models\Milestone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Milestone $milestone) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("private-project.{$this->milestone->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'milestone.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'milestone' => [
                'id' => $this->milestone->id,
                'project_id' => $this->milestone->project_id,
                'name' => $this->milestone->name,
                'status' => $this->milestone->status,
                'due_date' => $this->milestone->due_date,
                'progress' => $this->milestone->progress,
            ],
        ];
    }
}
