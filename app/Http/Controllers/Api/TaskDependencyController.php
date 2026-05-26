<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskDependencyController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $task->load([
            'blockedBy:id,title,status,priority',
            'blocking:id,title,status,priority',
        ]);

        return response()->json([
            'blocked_by' => $task->blockedBy->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'type' => $t->pivot->type,
            ]),
            'blocking' => $task->blocking->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'type' => $t->pivot->type,
            ]),
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'depends_on_id' => 'required|string|exists:tasks,id|different:'.$task->id,
            'type' => 'required|in:blocks,is_blocked_by,relates_to,duplicates',
        ]);

        // Prevent duplicate
        $exists = $task->blockedBy()
            ->wherePivot('depends_on_id', $data['depends_on_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Dependency already exists.'], 422);
        }

        $task->blockedBy()->attach($data['depends_on_id'], ['type' => $data['type']]);

        return response()->json(['message' => 'Dependency added.'], 201);
    }

    public function destroy(Task $task, Task $dependency): JsonResponse
    {
        $task->blockedBy()->detach($dependency->id);

        return response()->json(null, 204);
    }
}
