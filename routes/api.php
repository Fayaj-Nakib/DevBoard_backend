<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Protected
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Workspaces
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::post('workspaces/{workspace}/members',          [WorkspaceController::class, 'addMember']);
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember']);

    // Labels (workspace-scoped)
    Route::get('workspaces/{workspace}/labels',                    [LabelController::class, 'index']);
    Route::post('workspaces/{workspace}/labels',                   [LabelController::class, 'store']);
    Route::patch('workspaces/{workspace}/labels/{label}',          [LabelController::class, 'update']);
    Route::delete('workspaces/{workspace}/labels/{label}',         [LabelController::class, 'destroy']);

    // Projects
    Route::apiResource('workspaces/{workspace}/projects', ProjectController::class);

    // Milestones
    Route::apiResource('workspaces/{workspace}/projects/{project}/milestones', MilestoneController::class);

    // Tasks (reorder must be registered before the resource so {task} doesn't match "reorder")
    Route::patch('workspaces/{workspace}/projects/{project}/tasks/reorder', [TaskController::class, 'reorder']);
    Route::apiResource('workspaces/{workspace}/projects/{project}/tasks', TaskController::class);

    // Sub-tasks
    Route::get('workspaces/{workspace}/projects/{project}/tasks/{task}/subtasks',  [TaskController::class, 'indexSubtasks']);
    Route::post('workspaces/{workspace}/projects/{project}/tasks/{task}/subtasks', [TaskController::class, 'storeSubtask']);

    // Task — labels attach/detach
    Route::post('tasks/{task}/labels/{label}',   [LabelController::class, 'attach']);
    Route::delete('tasks/{task}/labels/{label}', [LabelController::class, 'detach']);

    // Task — watchers
    Route::post('tasks/{task}/watch',   [TaskController::class, 'watch']);
    Route::delete('tasks/{task}/watch', [TaskController::class, 'unwatch']);

    // Task — attachments
    Route::get('tasks/{task}/attachments',              [AttachmentController::class, 'index']);
    Route::post('tasks/{task}/attachments',             [AttachmentController::class, 'store']);
    Route::delete('tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Comments
    Route::apiResource('tasks/{task}/comments', CommentController::class)
        ->only(['index', 'store', 'destroy']);

    // Notifications
    Route::get('/notifications',                       [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all',            [NotificationController::class, 'readAll']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
