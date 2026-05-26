<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User       $user,
        public Collection $dueTodayTasks,
        public Collection $overdueTasks,
        public Collection $watchedActivityTasks,
        public Collection $mentions,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your DevBoard Daily Digest — ' . now()->format('M j, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest');
    }
}
