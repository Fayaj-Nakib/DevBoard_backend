<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BacklogController;
use App\Http\Controllers\Api\BulkTaskController;
use App\Http\Controllers\Api\BurndownController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectStatusController;
use App\Http\Controllers\Api\RecurringTaskController;
use App\Http\Controllers\Api\SprintController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskDependencyController;
use App\Http\Controllers\Api\TaskTemplateController;
use App\Http\Controllers\Api\TimelineController;
use App\Http\Controllers\Api\WorkloadController;
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
    Route::get('workspaces/{workspace}/members',           [WorkspaceController::class, 'listMembers']);

    // Labels (workspace-scoped)
    Route::get('workspaces/{workspace}/labels',           [LabelController::class, 'index']);
    Route::post('workspaces/{workspace}/labels',          [LabelController::class, 'store']);
    Route::patch('workspaces/{workspace}/labels/{label}', [LabelController::class, 'update']);
    Route::delete('workspaces/{workspace}/labels/{label}',[LabelController::class, 'destroy']);

    // Projects
    Route::apiResource('workspaces/{workspace}/projects', ProjectController::class);

    // Project statuses
    Route::get('workspaces/{workspace}/projects/{project}/statuses',                         [ProjectStatusController::class, 'index']);
    Route::post('workspaces/{workspace}/projects/{project}/statuses',                        [ProjectStatusController::class, 'store']);
    Route::patch('workspaces/{workspace}/projects/{project}/statuses/reorder',               [ProjectStatusController::class, 'reorder']);
    Route::patch('workspaces/{workspace}/projects/{project}/statuses/{status}',              [ProjectStatusController::class, 'update']);
    Route::delete('workspaces/{workspace}/projects/{project}/statuses/{status}',             [ProjectStatusController::class, 'destroy']);

    // Task templates
    Route::get('workspaces/{workspace}/projects/{project}/templates',                        [TaskTemplateController::class, 'index']);
    Route::post('workspaces/{workspace}/projects/{project}/templates',                       [TaskTemplateController::class, 'store']);
    Route::patch('workspaces/{workspace}/projects/{project}/templates/{template}',           [TaskTemplateController::class, 'update']);
    Route::delete('workspaces/{workspace}/projects/{project}/templates/{template}',          [TaskTemplateController::class, 'destroy']);

    // Backlog
    Route::get('workspaces/{workspace}/projects/{project}/backlog',                          [BacklogController::class, 'index']);
    Route::patch('workspaces/{workspace}/projects/{project}/backlog/reorder',                [BacklogController::class, 'reorder']);
    Route::patch('workspaces/{workspace}/projects/{project}/tasks/{task}/backlog-toggle',    [BacklogController::class, 'toggle']);

    // Timeline
    Route::get('workspaces/{workspace}/projects/{project}/timeline',                         [TimelineController::class, 'index']);

    // Calendar
    Route::get('workspaces/{workspace}/projects/{project}/calendar',                         [CalendarController::class, 'index']);

    // Workload
    Route::get('workspaces/{workspace}/projects/{project}/workload',                         [WorkloadController::class, 'index']);

    // Analytics / Stats
    Route::get('workspaces/{workspace}/projects/{project}/stats',                            [StatsController::class, 'projectStats']);
    Route::get('workspaces/{workspace}/stats',                                               [StatsController::class, 'workspaceStats']);
    Route::get('workspaces/{workspace}/projects/{project}/velocity',                         [StatsController::class, 'velocity']);

    // Milestones
    Route::apiResource('workspaces/{workspace}/projects/{project}/milestones', MilestoneController::class);

    // Sprints
    Route::get('workspaces/{workspace}/projects/{project}/sprints',                            [SprintController::class, 'index']);
    Route::post('workspaces/{workspace}/projects/{project}/sprints',                           [SprintController::class, 'store']);
    Route::get('workspaces/{workspace}/projects/{project}/sprints/{sprint}',                   [SprintController::class, 'show']);
    Route::patch('workspaces/{workspace}/projects/{project}/sprints/{sprint}',                 [SprintController::class, 'update']);
    Route::delete('workspaces/{workspace}/projects/{project}/sprints/{sprint}',                [SprintController::class, 'destroy']);
    Route::post('workspaces/{workspace}/projects/{project}/sprints/{sprint}/tasks',            [SprintController::class, 'addTask']);
    Route::delete('workspaces/{workspace}/projects/{project}/sprints/{sprint}/tasks/{task}',   [SprintController::class, 'removeTask']);
    Route::get('workspaces/{workspace}/projects/{project}/sprints/{sprint}/burndown',          [BurndownController::class, 'show']);

    // Tasks (static segments must be registered before the resource to avoid {task} binding)
    Route::patch('workspaces/{workspace}/projects/{project}/tasks/reorder', [TaskController::class, 'reorder']);
    Route::post('workspaces/{workspace}/projects/{project}/tasks/bulk',    [BulkTaskController::class, '__invoke']);
    Route::apiResource('workspaces/{workspace}/projects/{project}/tasks', TaskController::class);

    // Sub-tasks
    Route::get('workspaces/{workspace}/projects/{project}/tasks/{task}/subtasks',  [TaskController::class, 'indexSubtasks']);
    Route::post('workspaces/{workspace}/projects/{project}/tasks/{task}/subtasks', [TaskController::class, 'storeSubtask']);

    // Task — labels attach/detach
    Route::post('tasks/{task}/labels/{label}',   [LabelController::class, 'attach']);
    Route::delete('tasks/{task}/labels/{label}', [LabelController::class, 'detach']);

    // Task — recurrence
    Route::patch('tasks/{task}/recurrence', [RecurringTaskController::class, 'update']);

    // Task — watchers
    Route::post('tasks/{task}/watch',   [TaskController::class, 'watch']);
    Route::delete('tasks/{task}/watch', [TaskController::class, 'unwatch']);

    // Task — attachments
    Route::get('tasks/{task}/attachments',                [AttachmentController::class, 'index']);
    Route::post('tasks/{task}/attachments',               [AttachmentController::class, 'store']);
    Route::delete('tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Task — dependencies
    Route::get('tasks/{task}/dependencies',              [TaskDependencyController::class, 'index']);
    Route::post('tasks/{task}/dependencies',             [TaskDependencyController::class, 'store']);
    Route::delete('tasks/{task}/dependencies/{dependency}', [TaskDependencyController::class, 'destroy']);

    // Comments
    Route::apiResource('tasks/{task}/comments', CommentController::class)
        ->only(['index', 'store', 'destroy']);

    // Notifications
    Route::get('/notifications',                       [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all',            [NotificationController::class, 'readAll']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
