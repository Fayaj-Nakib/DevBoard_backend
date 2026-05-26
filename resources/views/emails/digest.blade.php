<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevBoard Daily Digest</title>
    <style>
        body { margin: 0; padding: 0; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #374151; }
        .wrapper { padding: 40px 16px; }
        .card { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #2563eb; padding: 28px 32px; color: #fff; }
        .header h1 { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .header p { margin: 0; font-size: 13px; opacity: 0.85; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; margin-bottom: 20px; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 10px; }
        .task-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .task-row:last-child { border-bottom: none; }
        .task-title { font-weight: 600; color: #111827; }
        .task-meta { font-size: 12px; color: #9ca3af; margin-top: 2px; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-high   { background: #fee2e2; color: #dc2626; }
        .badge-medium { background: #fef3c7; color: #b45309; }
        .badge-low    { background: #dcfce7; color: #16a34a; }
        .mention-row { padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .mention-row:last-child { border-bottom: none; }
        .mention-by { font-size: 12px; color: #9ca3af; margin-top: 2px; }
        .empty { color: #9ca3af; font-size: 13px; font-style: italic; }
        .footer { padding: 16px 32px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="wrapper">
<div class="card">
    <div class="header">
        <h1>Your Daily Digest</h1>
        <p>DevBoard &mdash; {{ now()->format('l, F j, Y') }}</p>
    </div>

    <div class="body">
        <p class="greeting">Hi <strong>{{ $user->name }}</strong>, here's your summary for today.</p>

        {{-- Due Today --}}
        <div class="section">
            <div class="section-title">Due Today ({{ $dueTodayTasks->count() }})</div>
            @forelse($dueTodayTasks as $task)
            <div class="task-row">
                <div>
                    <div class="task-title">{{ $task->title }}</div>
                    <div class="task-meta">
                        {{ $task->project->name ?? '' }}
                        &bull; <span class="badge badge-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span>
                    </div>
                </div>
            </div>
            @empty
            <p class="empty">No tasks due today.</p>
            @endforelse
        </div>

        {{-- Overdue --}}
        @if($overdueTasks->isNotEmpty())
        <div class="section">
            <div class="section-title" style="color: #dc2626;">Overdue ({{ $overdueTasks->count() }})</div>
            @foreach($overdueTasks as $task)
            <div class="task-row">
                <div>
                    <div class="task-title" style="color: #dc2626;">{{ $task->title }}</div>
                    <div class="task-meta">
                        {{ $task->project->name ?? '' }}
                        &bull; Due {{ $task->due_date?->format('M j') }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Watched tasks with activity --}}
        @if($watchedActivityTasks->isNotEmpty())
        <div class="section">
            <div class="section-title">Watched Tasks with Activity</div>
            @foreach($watchedActivityTasks as $task)
            <div class="task-row">
                <div>
                    <div class="task-title">{{ $task->title }}</div>
                    <div class="task-meta">{{ $task->project->name ?? '' }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Mentions --}}
        @if($mentions->isNotEmpty())
        <div class="section">
            <div class="section-title">Recent Mentions ({{ $mentions->count() }})</div>
            @foreach($mentions as $mention)
            <div class="mention-row">
                <div style="font-weight: 600; color: #111827;">{{ $mention->task->title ?? 'a task' }}</div>
                <div class="mention-by">{{ $mention->user->name }} mentioned you &bull; {{ $mention->created_at->diffForHumans() }}</div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="footer">
        DevBoard &bull; You received this because you have daily digest enabled.
    </div>
</div>
</div>
</body>
</html>
