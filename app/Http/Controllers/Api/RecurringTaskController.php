<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecurringTaskController extends Controller
{
    public function update(Request $request, Task $task): JsonResponse
    {
        $workspace = $task->project->workspace;
        /** @var User $user */
        $user = Auth::user();
        abort_if(! in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);

        $request->validate([
            'recurrence_rule' => 'nullable|in:daily,weekly,weekday,monthly',
            'recurrence_ends_at' => 'nullable|date|after_or_equal:today',
        ]);

        $task->update([
            'recurrence_rule' => $request->recurrence_rule ?: null,
            'recurrence_ends_at' => $request->filled('recurrence_rule') ? $request->recurrence_ends_at : null,
        ]);

        return response()->json($task->fresh());
    }
}
