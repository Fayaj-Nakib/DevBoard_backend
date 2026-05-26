<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

abstract class Controller
{
    /** Abort 403 if the authenticated user is not in $roles for this workspace. */
    protected function wsGate(
        Workspace $workspace,
        array $roles = ['owner', 'admin', 'member', 'guest'],
    ): User {
        /** @var User $user */
        $user = auth()->user();
        abort_if(! in_array($workspace->userRole($user), $roles), 403);

        return $user;
    }

    /**
     * Workspace gate that also enforces project-level membership for guests.
     * Guests must be an explicit project_member to access a project.
     */
    protected function projectGate(Workspace $workspace, Project $project): User
    {
        $user = $this->wsGate($workspace);
        if ($workspace->userRole($user) === 'guest') {
            abort_unless(
                ProjectMember::where('project_id', $project->id)
                    ->where('user_id', $user->id)
                    ->exists(),
                403
            );
        }

        return $user;
    }

    /**
     * For task-scoped routes (no Workspace/Project params) — resolve context from
     * the task relationship and apply the same guest project check.
     */
    protected function taskGuestCheck(Task $task): void
    {
        /** @var User $user */
        $user = auth()->user();
        $workspace = $task->project->workspace;
        $wsRole = $workspace->userRole($user);

        abort_if($wsRole === null, 403);

        if ($wsRole === 'guest') {
            abort_unless(
                ProjectMember::where('project_id', $task->project_id)
                    ->where('user_id', $user->id)
                    ->exists(),
                403
            );
        }
    }
}
