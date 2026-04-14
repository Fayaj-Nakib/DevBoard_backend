<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Assigned: {{ $task->title }}</title>
    <style>
        body {
            margin: 0; padding: 0;
            background: #f3f4f6;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #374151;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper { padding: 40px 16px; }
        .card {
            max-width: 560px; margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .header {
            background: #2563eb;
            padding: 32px 32px 28px;
            color: #ffffff;
        }
        .header h1 { margin: 0 0 6px; font-size: 20px; font-weight: 700; }
        .header p  { margin: 0; font-size: 13px; opacity: 0.85; }
        .body { padding: 32px; }
        .body p  { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
        .meta-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .meta-row:last-child { border-bottom: none; }
        .meta-label { color: #6b7280; }
        .meta-value { font-weight: 600; color: #111827; text-align: right; max-width: 60%; }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-high   { background: #fee2e2; color: #dc2626; }
        .badge-medium { background: #fef3c7; color: #b45309; }
        .badge-low    { background: #dcfce7; color: #16a34a; }
        .description-section { margin: 20px 0; }
        .description-section h2 { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 8px; }
        .description-text { font-size: 14px; line-height: 1.6; color: #374151; white-space: pre-wrap; background: #f9fafb; border-radius: 6px; padding: 12px 16px; }
        .footer {
            padding: 16px 32px;
            border-top: 1px solid #f3f4f6;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <h1>You've been assigned a task</h1>
            <p>DevBoard &mdash; Project Management</p>
        </div>

        <div class="body">
            <p>Hi <strong>{{ $task->assignee->name }}</strong>,</p>
            <p>
                <strong>{{ $task->creator->name }}</strong> has assigned you a new task
                in project <strong>{{ $task->project->name }}</strong>.
            </p>

            <div class="meta-box">
                <div class="meta-row">
                    <span class="meta-label">Task</span>
                    <span class="meta-value">{{ $task->title }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Priority</span>
                    <span class="meta-value">
                        <span class="badge badge-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Status</span>
                    <span class="meta-value">{{ ucwords(str_replace('_', ' ', $task->status)) }}</span>
                </div>
                @if($task->due_date)
                <div class="meta-row">
                    <span class="meta-label">Due Date</span>
                    <span class="meta-value">{{ $task->due_date->format('M j, Y') }}</span>
                </div>
                @endif
            </div>

            @if($task->description)
            <div class="description-section">
                <h2>Description</h2>
                <div class="description-text">{{ $task->description }}</div>
            </div>
            @endif
        </div>

        <div class="footer">
            DevBoard &bull; You received this email because a task was assigned to you.
        </div>
    </div>
</div>
</body>
</html>
