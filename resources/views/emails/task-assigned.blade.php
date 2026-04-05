<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; padding: 32px; color: #333;">
    <h2>You have been assigned a task</h2>
    <p><strong>Task:</strong> {{ $task->title }}</p>
    <p><strong>Project:</strong> {{ $task->project->name }}</p>
    <p><strong>Priority:</strong> {{ ucfirst($task->priority) }}</p>
    @if($task->due_date)
    <p><strong>Due:</strong> {{ $task->due_date->format('M d, Y') }}</p>
    @endif
    <p style="margin-top: 24px; color: #666;">Log in to DevBoard to view your task.</p>
</body>
</html>
