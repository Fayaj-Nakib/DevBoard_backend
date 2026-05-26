<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'            => 'required|string|min:2|max:200',
            'workspace_id' => 'required|string|exists:workspaces,id',
        ]);

        /** @var \App\Models\User $user */
        $user      = Auth::user();
        $q         = trim($request->q);
        $workspace = Workspace::findOrFail($request->workspace_id);

        // Confirm user is a member of this workspace
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin', 'member']), 403);

        $projectIds = $workspace->projects()->pluck('id');
        $like       = '%' . $q . '%';

        // Tasks — search title and description (ILIKE for PostgreSQL)
        $tasks = DB::table('tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('parent_id')
            ->where(function ($query) use ($like) {
                $query->whereRaw('LOWER(title) LIKE ?', [strtolower($like)])
                      ->orWhereRaw('LOWER(description) LIKE ?', [strtolower($like)]);
            })
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->select('tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.project_id', 'projects.name as project_name')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'type'         => 'task',
                'id'           => $t->id,
                'title'        => $t->title,
                'status'       => $t->status,
                'priority'     => $t->priority,
                'project_id'   => $t->project_id,
                'project_name' => $t->project_name,
            ]);

        // Projects
        $projects = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->whereRaw('LOWER(name) LIKE ?', [strtolower($like)])
            ->select('id', 'name', 'status', 'description')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'type'        => 'project',
                'id'          => $p->id,
                'title'       => $p->name,
                'description' => $p->description,
            ]);

        // Comments
        $comments = DB::table('comments')
            ->join('tasks', 'comments.task_id', '=', 'tasks.id')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->whereIn('tasks.project_id', $projectIds)
            ->whereRaw('LOWER(comments.body) LIKE ?', [strtolower($like)])
            ->select(
                'comments.id', 'comments.body', 'comments.task_id',
                'tasks.title as task_title', 'tasks.project_id',
                'users.name as author'
            )
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'type'       => 'comment',
                'id'         => $c->id,
                'title'      => substr($c->body, 0, 80),
                'task_id'    => $c->task_id,
                'task_title' => $c->task_title,
                'project_id' => $c->project_id,
                'author'     => $c->author,
            ]);

        // Members — search by name and email across workspace
        $members = DB::table('workspace_members')
            ->join('users', 'workspace_members.user_id', '=', 'users.id')
            ->where('workspace_members.workspace_id', $workspace->id)
            ->where(function ($query) use ($like) {
                $query->whereRaw('LOWER(users.name) LIKE ?', [strtolower($like)])
                      ->orWhereRaw('LOWER(users.email) LIKE ?', [strtolower($like)]);
            })
            ->select('users.id', 'users.name', 'users.email')
            ->limit(5)
            ->get()
            ->map(fn($m) => [
                'type'  => 'member',
                'id'    => $m->id,
                'title' => $m->name,
                'email' => $m->email,
            ]);

        return response()->json([
            'query'    => $q,
            'tasks'    => $tasks,
            'projects' => $projects,
            'comments' => $comments,
            'members'  => $members,
        ]);
    }
}
