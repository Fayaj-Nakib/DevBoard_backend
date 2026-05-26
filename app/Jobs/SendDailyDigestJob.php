<?php

namespace App\Jobs;

use App\Mail\DigestMail;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 60;

    public function __construct(public User $user) {}

    public function handle(): void
    {
        $today     = now()->toDateString();
        $yesterday = now()->subDay();

        // Tasks due today assigned to this user
        $dueTodayTasks = Task::query()
            ->whereHas('assignees', fn($q) => $q->where('users.id', $this->user->id))
            ->whereDate('due_date', $today)
            ->where('status', '!=', 'done')
            ->with('project:id,name')
            ->get(['id', 'title', 'priority', 'project_id', 'due_date']);

        // Overdue tasks assigned to this user
        $overdueTasks = Task::query()
            ->whereHas('assignees', fn($q) => $q->where('users.id', $this->user->id))
            ->whereDate('due_date', '<', $today)
            ->where('status', '!=', 'done')
            ->with('project:id,name')
            ->get(['id', 'title', 'priority', 'project_id', 'due_date']);

        // Watched tasks with recent activity
        $watchedActivityTasks = Task::query()
            ->whereHas('watchers', fn($q) => $q->where('users.id', $this->user->id))
            ->where('updated_at', '>=', $yesterday)
            ->whereNotIn('id', $dueTodayTasks->pluck('id')->merge($overdueTasks->pluck('id')))
            ->with('project:id,name')
            ->get(['id', 'title', 'project_id', 'updated_at']);

        // @mentions in last 24h
        $mentions = Comment::query()
            ->where('body', 'like', "%@{$this->user->name}%")
            ->where('created_at', '>=', $yesterday)
            ->with(['task:id,title,project_id', 'task.project:id,name', 'user:id,name'])
            ->get();

        // Skip empty digests
        if ($dueTodayTasks->isEmpty()
            && $overdueTasks->isEmpty()
            && $watchedActivityTasks->isEmpty()
            && $mentions->isEmpty()
        ) {
            return;
        }

        Mail::to($this->user->email)->send(new DigestMail(
            $this->user,
            $dueTodayTasks,
            $overdueTasks,
            $watchedActivityTasks,
            $mentions,
        ));

        $this->user->update(['last_digest_sent' => $today]);
    }
}
