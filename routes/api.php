<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
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
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Workspaces
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::post('workspaces/{workspace}/members',          [WorkspaceController::class, 'addMember']);
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember']);

    // Projects
    Route::apiResource('workspaces/{workspace}/projects', ProjectController::class);

    // Tasks (reorder must be registered before the resource so {task} doesn't match "reorder")
    Route::patch('workspaces/{workspace}/projects/{project}/tasks/reorder', [TaskController::class, 'reorder']);
    Route::apiResource('workspaces/{workspace}/projects/{project}/tasks', TaskController::class);

    // Comments
    Route::apiResource('tasks/{task}/comments', CommentController::class)
        ->only(['index', 'store', 'destroy']);

    // Notifications
    Route::get('/notifications',                       [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all',            [NotificationController::class, 'readAll']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
