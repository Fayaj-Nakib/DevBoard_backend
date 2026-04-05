<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Task;
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

        $comment = Comment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body'    => $request->body,
        ]);

        return response()->json($comment->load('user'), 201);
    }

    public function destroy(Request $request, Task $task, Comment $comment): JsonResponse
    {
        abort_if($comment->user_id !== $request->user()->id, 403);
        $comment->delete();
        return response()->json(null, 204);
    }
}
