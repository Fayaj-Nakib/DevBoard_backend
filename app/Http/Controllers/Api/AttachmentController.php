<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $this->taskGuestCheck($task);
        return response()->json(
            $task->attachments()->with('uploader:id,name')->get()
        );
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20 MB max
        ]);

        $workspace = $task->project->workspace;
        /** @var \App\Models\User $user */
        $user = $request->user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);

        $file = $request->file('file');
        $path = $file->store("attachments/{$task->id}", 'public');

        $attachment = $task->attachments()->create([
            'uploaded_by'   => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);

        return response()->json($attachment->load('uploader:id,name'), 201);
    }

    public function destroy(Request $request, Task $task, Attachment $attachment): JsonResponse
    {
        $workspace = $task->project->workspace;
        /** @var \App\Models\User $user */
        $user       = $request->user();
        $role       = $workspace->userRole($user);
        $isUploader = $attachment->uploaded_by === $user->id;
        $isAdmin    = in_array($role, ['owner', 'admin']);

        abort_if(!$isUploader && !$isAdmin, 403);

        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();

        return response()->json(null, 204);
    }
}
