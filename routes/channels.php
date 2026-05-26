<?php

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channels are authenticated here. Private channels require the user to be
| authenticated and authorized. The closure receives the authenticated user
| and any wildcards extracted from the channel name.
|
*/

// Personal channel — user receives their own notifications
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

// Workspace channel — any workspace member may subscribe
Broadcast::channel('private-workspace.{workspaceId}', function ($user, $workspaceId) {
    $workspace = Workspace::find($workspaceId);
    if (! $workspace) {
        return false;
    }

    return $workspace->userRole($user) !== null;
});

// Project channel — any workspace member with access to this project may subscribe
Broadcast::channel('private-project.{projectId}', function ($user, $projectId) {
    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }
    $workspace = $project->workspace;

    return $workspace->userRole($user) !== null;
});
