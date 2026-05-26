<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentCreated as CommentCreatedEvent;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateAutomationRules;
use App\Jobs\SendMentionNotification;
use App\Jobs\SendTaskWatcherNotification;
use App\Jobs\WebhookDispatchJob;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        return response()->json(
            $task->comments()->with('user:id,name,email')->get()
        );
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $request->validate(['body' => 'required|string']);

        /** @var User $author */
        $author = $request->user();

        $comment = Comment::create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'body'    => $request->body,
        ]);

        $comment->load('user', 'task.project');

        // Parse @mentions and notify matched workspace members
        $this->dispatchMentions($comment, $author->id, $task);

        // Notify watchers that a comment was added
        foreach ($task->watchers as $watcher) {
            if ($watcher->id !== $author->id) {
                SendTaskWatcherNotification::dispatch($task, $watcher, 'comment_added', [
                    'comment_by' => $author->name,
                ]);
            }
        }

        EvaluateAutomationRules::dispatch($task, 'comment_added', [], $author->id);

        WebhookDispatchJob::dispatch(
            $task->project->workspace_id,
            'comment.created',
            [
                'comment_id' => $comment->id,
                'task_id'    => $task->id,
                'author_id'  => $author->id,
            ]
        );

        broadcast(new CommentCreatedEvent($comment))->toOthers();

        return response()->json($comment->load('user'), 201);
    }

    public function destroy(Request $request, Task $task, Comment $comment): JsonResponse
    {
        abort_if($comment->task_id !== $task->id, 404);
        abort_if($comment->user_id !== $request->user()->id, 403);
        $comment->delete();
        return response()->json(null, 204);
    }

    private function dispatchMentions(Comment $comment, string $authorId, Task $task): void
    {
        preg_match_all('/@([\w]+(?:\s[\w]+)?)/u', $comment->body, $matches);

        if (empty($matches[1])) {
            return;
        }

        // Scope mentions to workspace members only
        $workspaceUserIds = $task->project->workspace
            ->members()
            ->pluck('user_id')
            ->all();

        $mentioned = User::whereIn('id', $workspaceUserIds)
            ->where('id', '!=', $authorId)
            ->where(function ($q) use ($matches) {
                foreach ($matches[1] as $name) {
                    $q->orWhere('name', 'like', trim($name) . '%');
                }
            })
            ->get();

        foreach ($mentioned as $user) {
            SendMentionNotification::dispatch($comment, $user);
        }
    }
}

