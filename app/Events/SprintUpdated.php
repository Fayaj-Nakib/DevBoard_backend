<?php

namespace App\Events;

use App\Models\Sprint;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SprintUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Sprint $sprint) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("private-project.{$this->sprint->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sprint.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'sprint' => [
                'id'         => $this->sprint->id,
                'project_id' => $this->sprint->project_id,
                'name'       => $this->sprint->name,
                'status'     => $this->sprint->status,
                'start_date' => $this->sprint->start_date,
                'end_date'   => $this->sprint->end_date,
            ],
        ];
    }
}
