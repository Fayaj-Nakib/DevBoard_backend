<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression suite for the cross-tenant IDOR: an attacker who is a non-guest
 * member of workspace A could reach a project (and everything under it)
 * belonging to an unrelated workspace B by hitting
 * /api/workspaces/{A}/projects/{B's project}/... — because the project-scoped
 * controller gates checked "does the caller have a role in {workspace}?" but
 * never "does {project} actually belong to {workspace}?".
 *
 * Every test below acts as an admin of workspace A (the attacker's own,
 * legitimate workspace) against a project/resource that belongs to
 * workspace B (the victim, which the attacker has never been a member of).
 * All of these must 404 and must leave B's data untouched.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private Workspace $workspaceA;

    private Workspace $workspaceB;

    private User $victimOwner;

    private Project $projectB;

    private Task $taskB;

    private Milestone $milestoneB;

    private Sprint $sprintB;

    private ProjectStatus $statusB;

    private TaskTemplate $templateB;

    private AutomationRule $ruleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->create();
        $this->workspaceA = Workspace::factory()->create(['owner_id' => $this->attacker->id]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->attacker->id,
            'role' => 'admin',
        ]);

        $this->victimOwner = User::factory()->create();
        $this->workspaceB = Workspace::factory()->create(['owner_id' => $this->victimOwner->id]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->victimOwner->id,
            'role' => 'owner',
        ]);

        $this->projectB = Project::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->victimOwner->id,
        ]);

        $this->taskB = Task::factory()->create([
            'project_id' => $this->projectB->id,
            'created_by' => $this->victimOwner->id,
            'title' => 'Victim task',
            'status' => 'todo',
            'position' => 1,
        ]);

        $this->milestoneB = Milestone::create([
            'project_id' => $this->projectB->id,
            'created_by' => $this->victimOwner->id,
            'name' => 'Victim milestone',
        ]);

        $this->sprintB = Sprint::create([
            'project_id' => $this->projectB->id,
            'created_by' => $this->victimOwner->id,
            'name' => 'Victim sprint',
            'status' => 'planning',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->statusB = ProjectStatus::create([
            'project_id' => $this->projectB->id,
            'name' => 'Victim status',
            'color' => '#9CA3AF',
            'position' => 5,
            'is_default' => false,
            'is_done' => false,
            'slug' => null,
        ]);

        $this->templateB = TaskTemplate::create([
            'project_id' => $this->projectB->id,
            'created_by' => $this->victimOwner->id,
            'name' => 'Victim template',
            'priority' => 'medium',
        ]);

        $this->ruleB = AutomationRule::create([
            'project_id' => $this->projectB->id,
            'name' => 'Victim rule',
            'is_active' => true,
            'trigger_type' => 'task_created',
            'action_type' => 'send_notification',
        ]);
    }

    /** /api/workspaces/{attacker's own workspace}/projects/{victim's project}/... */
    private function base(): string
    {
        return "/api/workspaces/{$this->workspaceA->id}/projects/{$this->projectB->id}";
    }

    private function asAttacker()
    {
        return $this->actingAs($this->attacker);
    }

    // ===================================================================
    // TaskController
    // ===================================================================

    public function test_cannot_list_tasks_of_foreign_project(): void
    {
        $this->asAttacker()->getJson($this->base().'/tasks')->assertNotFound();
    }

    public function test_cannot_show_foreign_task(): void
    {
        $this->asAttacker()->getJson($this->base()."/tasks/{$this->taskB->id}")->assertNotFound();
    }

    public function test_cannot_create_task_in_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/tasks', ['title' => 'Injected task'])
            ->assertNotFound();

        $this->assertDatabaseMissing('tasks', ['title' => 'Injected task']);
    }

    public function test_cannot_update_foreign_task(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/tasks/{$this->taskB->id}", ['title' => 'Hacked', 'status' => 'done'])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'title' => 'Victim task', 'status' => 'todo']);
    }

    public function test_cannot_delete_foreign_task(): void
    {
        $this->asAttacker()
            ->deleteJson($this->base()."/tasks/{$this->taskB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id]);
    }

    public function test_cannot_create_subtask_under_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base()."/tasks/{$this->taskB->id}/subtasks", ['title' => 'Injected subtask'])
            ->assertNotFound();

        $this->assertDatabaseMissing('tasks', ['title' => 'Injected subtask']);
    }

    public function test_cannot_reorder_foreign_project_tasks(): void
    {
        $this->asAttacker()
            ->patchJson($this->base().'/tasks/reorder', [
                'tasks' => [['id' => $this->taskB->id, 'status' => 'done', 'position' => 99]],
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'position' => 1, 'status' => 'todo']);
    }

    // ===================================================================
    // BulkTaskController
    // ===================================================================

    public function test_cannot_bulk_delete_foreign_tasks(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/tasks/bulk', [
                'task_ids' => [$this->taskB->id],
                'action' => 'delete',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id]);
    }

    public function test_cannot_bulk_move_status_foreign_tasks(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/tasks/bulk', [
                'task_ids' => [$this->taskB->id],
                'action' => 'move_status',
                'payload' => ['status' => 'done'],
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'status' => 'todo']);
    }

    public function test_cannot_bulk_assign_foreign_tasks(): void
    {
        $outsider = User::factory()->create();

        $this->asAttacker()
            ->postJson($this->base().'/tasks/bulk', [
                'task_ids' => [$this->taskB->id],
                'action' => 'assign',
                'payload' => ['user_id' => $outsider->id],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('task_assignees', ['task_id' => $this->taskB->id, 'user_id' => $outsider->id]);
    }

    public function test_cannot_bulk_add_label_to_foreign_tasks(): void
    {
        $label = Label::create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->victimOwner->id,
            'name' => 'victim-label',
        ]);

        $this->asAttacker()
            ->postJson($this->base().'/tasks/bulk', [
                'task_ids' => [$this->taskB->id],
                'action' => 'add_label',
                'payload' => ['label_id' => $label->id],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('label_task', ['task_id' => $this->taskB->id, 'label_id' => $label->id]);
    }

    public function test_cannot_bulk_set_milestone_on_foreign_tasks(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/tasks/bulk', [
                'task_ids' => [$this->taskB->id],
                'action' => 'set_milestone',
                'payload' => ['milestone_id' => $this->milestoneB->id],
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'milestone_id' => null]);
    }

    // ===================================================================
    // MilestoneController
    // ===================================================================

    public function test_cannot_list_foreign_milestones(): void
    {
        $this->asAttacker()->getJson($this->base().'/milestones')->assertNotFound();
    }

    public function test_cannot_create_milestone_in_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/milestones', ['name' => 'Injected milestone'])
            ->assertNotFound();

        $this->assertDatabaseMissing('milestones', ['name' => 'Injected milestone']);
    }

    public function test_cannot_show_foreign_milestone(): void
    {
        $this->asAttacker()->getJson($this->base()."/milestones/{$this->milestoneB->id}")->assertNotFound();
    }

    public function test_cannot_update_foreign_milestone(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/milestones/{$this->milestoneB->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('milestones', ['id' => $this->milestoneB->id, 'name' => 'Victim milestone']);
    }

    public function test_cannot_delete_foreign_milestone(): void
    {
        $this->asAttacker()
            ->deleteJson($this->base()."/milestones/{$this->milestoneB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('milestones', ['id' => $this->milestoneB->id]);
    }

    // ===================================================================
    // SprintController
    // ===================================================================

    public function test_cannot_list_foreign_sprints(): void
    {
        $this->asAttacker()->getJson($this->base().'/sprints')->assertNotFound();
    }

    public function test_cannot_create_sprint_in_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/sprints', ['name' => 'Injected sprint'])
            ->assertNotFound();

        $this->assertDatabaseMissing('sprints', ['name' => 'Injected sprint']);
    }

    public function test_cannot_show_foreign_sprint(): void
    {
        $this->asAttacker()->getJson($this->base()."/sprints/{$this->sprintB->id}")->assertNotFound();
    }

    public function test_cannot_update_foreign_sprint(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/sprints/{$this->sprintB->id}", ['name' => 'Hacked', 'status' => 'active'])
            ->assertNotFound();

        $this->assertDatabaseHas('sprints', ['id' => $this->sprintB->id, 'name' => 'Victim sprint', 'status' => 'planning']);
    }

    public function test_cannot_delete_foreign_sprint(): void
    {
        $this->asAttacker()
            ->deleteJson($this->base()."/sprints/{$this->sprintB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('sprints', ['id' => $this->sprintB->id]);
    }

    public function test_cannot_add_task_to_foreign_sprint(): void
    {
        $this->asAttacker()
            ->postJson($this->base()."/sprints/{$this->sprintB->id}/tasks", ['task_ids' => [$this->taskB->id]])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'sprint_id' => null]);
    }

    public function test_cannot_remove_task_from_foreign_sprint(): void
    {
        $this->taskB->update(['sprint_id' => $this->sprintB->id]);

        $this->asAttacker()
            ->deleteJson($this->base()."/sprints/{$this->sprintB->id}/tasks/{$this->taskB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'sprint_id' => $this->sprintB->id]);
    }

    // ===================================================================
    // ProjectStatusController
    // ===================================================================

    public function test_cannot_list_foreign_statuses(): void
    {
        $this->asAttacker()->getJson($this->base().'/statuses')->assertNotFound();
    }

    public function test_cannot_create_status_in_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/statuses', ['name' => 'Injected status'])
            ->assertNotFound();

        $this->assertDatabaseMissing('project_statuses', ['name' => 'Injected status']);
    }

    public function test_cannot_update_foreign_status(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/statuses/{$this->statusB->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('project_statuses', ['id' => $this->statusB->id, 'name' => 'Victim status']);
    }

    public function test_cannot_delete_foreign_status(): void
    {
        $this->asAttacker()
            ->deleteJson($this->base()."/statuses/{$this->statusB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('project_statuses', ['id' => $this->statusB->id]);
    }

    public function test_cannot_reorder_foreign_statuses(): void
    {
        $this->asAttacker()
            ->patchJson($this->base().'/statuses/reorder', [
                'statuses' => [['id' => $this->statusB->id, 'position' => 99]],
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('project_statuses', ['id' => $this->statusB->id, 'position' => 5]);
    }

    // ===================================================================
    // StatsController
    // ===================================================================

    public function test_cannot_view_foreign_project_stats(): void
    {
        $this->asAttacker()->getJson($this->base().'/stats')->assertNotFound();
    }

    public function test_cannot_view_foreign_project_velocity(): void
    {
        $this->asAttacker()->getJson($this->base().'/velocity')->assertNotFound();
    }

    // ===================================================================
    // TimelineController / WorkloadController / CalendarController
    // ===================================================================

    public function test_cannot_view_foreign_timeline(): void
    {
        $this->asAttacker()->getJson($this->base().'/timeline')->assertNotFound();
    }

    public function test_cannot_view_foreign_workload(): void
    {
        $this->asAttacker()->getJson($this->base().'/workload')->assertNotFound();
    }

    public function test_cannot_view_foreign_calendar(): void
    {
        $this->asAttacker()->getJson($this->base().'/calendar')->assertNotFound();
    }

    // ===================================================================
    // TaskTemplateController
    // ===================================================================

    public function test_cannot_list_foreign_templates(): void
    {
        $this->asAttacker()->getJson($this->base().'/templates')->assertNotFound();
    }

    public function test_cannot_create_template_in_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/templates', ['name' => 'Injected template'])
            ->assertNotFound();

        $this->assertDatabaseMissing('task_templates', ['name' => 'Injected template']);
    }

    public function test_cannot_update_foreign_template(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/templates/{$this->templateB->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('task_templates', ['id' => $this->templateB->id, 'name' => 'Victim template']);
    }

    public function test_cannot_delete_foreign_template(): void
    {
        $this->asAttacker()
            ->deleteJson($this->base()."/templates/{$this->templateB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('task_templates', ['id' => $this->templateB->id]);
    }

    // ===================================================================
    // TimeLogController — found during the audit of this same bug class;
    // projectLogs() only checked the caller's role in {workspace}, never that
    // {project} actually belonged to it.
    // ===================================================================

    public function test_cannot_view_foreign_project_time_logs(): void
    {
        $this->asAttacker()->getJson($this->base().'/time-logs')->assertNotFound();
    }

    // ===================================================================
    // ActivityController
    // ===================================================================

    public function test_cannot_view_foreign_project_activity(): void
    {
        $this->asAttacker()->getJson($this->base().'/activity')->assertNotFound();
    }

    // ===================================================================
    // BacklogController
    // ===================================================================

    public function test_cannot_list_foreign_backlog(): void
    {
        $this->asAttacker()->getJson($this->base().'/backlog')->assertNotFound();
    }

    public function test_cannot_reorder_foreign_backlog(): void
    {
        $this->asAttacker()
            ->patchJson($this->base().'/backlog/reorder', [
                'tasks' => [['id' => $this->taskB->id, 'position' => 1]],
            ])
            ->assertNotFound();
    }

    public function test_cannot_toggle_foreign_task_backlog_flag(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/tasks/{$this->taskB->id}/backlog-toggle")
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $this->taskB->id, 'is_backlog' => false]);
    }

    // ===================================================================
    // BurndownController
    // ===================================================================

    public function test_cannot_view_foreign_sprint_burndown(): void
    {
        $this->asAttacker()
            ->getJson($this->base()."/sprints/{$this->sprintB->id}/burndown")
            ->assertNotFound();
    }

    // ===================================================================
    // AutomationRuleController
    // ===================================================================

    public function test_cannot_list_foreign_automation_rules(): void
    {
        $this->asAttacker()->getJson($this->base().'/automation-rules')->assertNotFound();
    }

    public function test_cannot_create_automation_rule_in_foreign_project(): void
    {
        $this->asAttacker()
            ->postJson($this->base().'/automation-rules', [
                'name' => 'Injected rule',
                'trigger_type' => 'task_created',
                'action_type' => 'send_notification',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('automation_rules', ['name' => 'Injected rule']);
    }

    public function test_cannot_show_foreign_automation_rule(): void
    {
        $this->asAttacker()->getJson($this->base()."/automation-rules/{$this->ruleB->id}")->assertNotFound();
    }

    public function test_cannot_update_foreign_automation_rule(): void
    {
        $this->asAttacker()
            ->patchJson($this->base()."/automation-rules/{$this->ruleB->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('automation_rules', ['id' => $this->ruleB->id, 'name' => 'Victim rule']);
    }

    public function test_cannot_delete_foreign_automation_rule(): void
    {
        $this->asAttacker()
            ->deleteJson($this->base()."/automation-rules/{$this->ruleB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('automation_rules', ['id' => $this->ruleB->id]);
    }

    // ===================================================================
    // ProjectController (same bug class, found while auditing — update/destroy
    // never verified the project belonged to the workspace in the URL either)
    // ===================================================================

    public function test_cannot_update_foreign_project(): void
    {
        $this->asAttacker()
            ->patchJson($this->base(), ['name' => 'Hacked project name'])
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $this->projectB->id, 'name' => $this->projectB->name]);
    }

    public function test_cannot_delete_foreign_project(): void
    {
        $this->asAttacker()->deleteJson($this->base())->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $this->projectB->id]);
    }
}
