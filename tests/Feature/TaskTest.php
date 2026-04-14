<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Project $project;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);

        // The gate() checks workspace membership, so we must add the user as owner.
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'role'         => 'owner',
        ]);

        $this->project = Project::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by'   => $this->user->id,
        ]);

        $this->base = "/api/workspaces/{$this->workspace->id}/projects/{$this->project->id}/tasks";
    }

    // ------------------------------------------------------------------ index

    public function test_lists_tasks_grouped_by_status(): void
    {
        Task::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status'     => 'todo',
        ]);
        Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status'     => 'in_progress',
        ]);

        $this->actingAs($this->user)
            ->getJson($this->base)
            ->assertOk()
            ->assertJsonCount(2, 'todo')
            ->assertJsonCount(1, 'in_progress');
    }

    public function test_non_member_cannot_list_tasks(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson($this->base)
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_tasks(): void
    {
        $this->getJson($this->base)->assertUnauthorized();
    }

    // ------------------------------------------------------------------ store

    public function test_can_create_task(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson($this->base, [
                'title'    => 'Build login page',
                'priority' => 'high',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Build login page')
            ->assertJsonPath('priority', 'high')
            ->assertJsonPath('status', 'todo');

        $this->assertDatabaseHas('tasks', [
            'title'      => 'Build login page',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_create_task_requires_title(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->base, ['priority' => 'low'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_task_rejects_invalid_priority(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->base, ['title' => 'Task', 'priority' => 'urgent'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_task_dispatches_notification_when_assigned(): void
    {
        $assignee = User::factory()->create();

        // Queue is set to 'sync' in phpunit.xml so the job runs inline.
        $this->actingAs($this->user)
            ->postJson($this->base, [
                'title'       => 'Assigned task',
                'assignee_id' => $assignee->id,
            ])
            ->assertStatus(201);

        // Notification record should be created by the job.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
        ]);
    }

    // ------------------------------------------------------------------ show

    public function test_can_show_task_with_comments(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("{$this->base}/{$task->id}")
            ->assertOk()
            ->assertJsonPath('id', $task->id)
            ->assertJsonStructure(['id', 'title', 'comments', 'assignee', 'creator']);
    }

    // ------------------------------------------------------------------ update

    public function test_can_update_task_status_and_title(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status'     => 'todo',
        ]);

        $this->actingAs($this->user)
            ->patchJson("{$this->base}/{$task->id}", [
                'status' => 'in_progress',
                'title'  => 'Updated title',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('title', 'Updated title');

        $this->assertDatabaseHas('tasks', [
            'id'     => $task->id,
            'status' => 'in_progress',
            'title'  => 'Updated title',
        ]);
    }

    public function test_updating_assignee_dispatches_notification(): void
    {
        $task     = Task::factory()->create([
            'project_id'  => $this->project->id,
            'created_by'  => $this->user->id,
            'assignee_id' => null,
        ]);
        $assignee = User::factory()->create();

        $this->actingAs($this->user)
            ->patchJson("{$this->base}/{$task->id}", ['assignee_id' => $assignee->id])
            ->assertOk();

        $this->assertDatabaseHas('notifications', ['user_id' => $assignee->id]);
    }

    // ------------------------------------------------------------------ destroy

    public function test_can_delete_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("{$this->base}/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    // ------------------------------------------------------------------ reorder

    public function test_can_reorder_tasks_across_columns(): void
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status'     => 'todo',
            'position'   => 1,
        ]);
        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status'     => 'todo',
            'position'   => 2,
        ]);

        $reorderUrl = "/api/workspaces/{$this->workspace->id}/projects/{$this->project->id}/tasks/reorder";

        $this->actingAs($this->user)
            ->patchJson($reorderUrl, [
                'tasks' => [
                    ['id' => $task1->id, 'status' => 'in_progress', 'position' => 1],
                    ['id' => $task2->id, 'status' => 'todo',        'position' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Reordered.');

        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'status' => 'in_progress', 'position' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'status' => 'todo',        'position' => 1]);
    }

    public function test_reorder_validates_payload(): void
    {
        $reorderUrl = "/api/workspaces/{$this->workspace->id}/projects/{$this->project->id}/tasks/reorder";

        $this->actingAs($this->user)
            ->patchJson($reorderUrl, ['tasks' => [['id' => 'uuid', 'status' => 'invalid']]])
            ->assertUnprocessable();
    }
}
