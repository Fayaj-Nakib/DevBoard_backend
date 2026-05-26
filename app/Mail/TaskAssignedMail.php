<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Task $task, public User $assignee) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Task assigned: '.$this->task->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-assigned');
    }
}
