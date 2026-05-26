<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\ImportJob;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\ProjectStatus;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $importJobId,
        public string $filePath,
        public string $format,
    ) {}

    public function handle(): void
    {
        /** @var ImportJob $importJob */
        $importJob = ImportJob::findOrFail($this->importJobId);
        $importJob->update(['status' => 'processing']);

        try {
            $content = Storage::get($this->filePath);
            $tasksCreated = match ($this->format) {
                'json' => $this->importJson($importJob, $content),
                'csv' => $this->importCsv($importJob, $content),
            };

            Storage::delete($this->filePath);

            $importJob->update([
                'status' => 'completed',
                'tasks_created' => $tasksCreated,
            ]);

            $this->notifyUser($importJob, $tasksCreated);
        } catch (\Throwable $e) {
            Storage::delete($this->filePath);
            $importJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function importJson(ImportJob $importJob, string $content): int
    {
        $data = json_decode($content, true);
        $project = $importJob->project;
        $userId = $importJob->user_id;

        return DB::transaction(function () use ($data, $project, $userId) {
            $statusMap = [];
            $milestoneMap = [];
            $sprintMap = [];
            $tasksCreated = 0;

            // Import statuses — match by name or create new
            foreach ($data['statuses'] ?? [] as $s) {
                $existing = $project->statuses()->where('name', $s['name'])->first();
                if ($existing) {
                    $statusMap[$s['id']] = $existing->id;
                } else {
                    $new = ProjectStatus::create([
                        'project_id' => $project->id,
                        'name' => $s['name'],
                        'color' => $s['color'] ?? '#9CA3AF',
                        'position' => $s['position'] ?? 99,
                        'is_default' => false,
                        'is_done' => $s['is_done'] ?? false,
                        'slug' => $s['slug'] ?? Str::slug($s['name']),
                    ]);
                    $statusMap[$s['id']] = $new->id;
                }
            }

            // Import milestones
            foreach ($data['milestones'] ?? [] as $m) {
                $new = Milestone::create([
                    'project_id' => $project->id,
                    'name' => $m['name'],
                    'description' => $m['description'] ?? null,
                    'due_date' => $m['due_date'] ?? null,
                    'status' => $m['status'] ?? 'open',
                ]);
                $milestoneMap[$m['id']] = $new->id;
            }

            // Import sprints
            foreach ($data['sprints'] ?? [] as $s) {
                $new = Sprint::create([
                    'project_id' => $project->id,
                    'name' => $s['name'],
                    'goal' => $s['goal'] ?? null,
                    'start_date' => $s['start_date'] ?? null,
                    'end_date' => $s['end_date'] ?? null,
                    'status' => $s['status'] ?? 'planned',
                ]);
                $sprintMap[$s['id']] = $new->id;
            }

            // Import tasks (flat — no parent re-mapping to keep it simple)
            $defaultStatus = $project->statuses()->where('is_default', true)->first()
                ?? $project->statuses()->first();

            foreach ($data['tasks'] ?? [] as $t) {
                Task::create([
                    'project_id' => $project->id,
                    'created_by' => $userId,
                    'title' => $t['title'],
                    'description' => $t['description'] ?? null,
                    'status' => $t['status'] ?? 'todo',
                    'priority' => $t['priority'] ?? 'medium',
                    'position' => $t['position'] ?? 0,
                    'due_date' => $t['due_date'] ?? null,
                    'started_at' => $t['started_at'] ?? null,
                    'estimate' => $t['estimate'] ?? null,
                    'project_status_id' => isset($t['project_status_id']) && isset($statusMap[$t['project_status_id']])
                        ? $statusMap[$t['project_status_id']]
                        : $defaultStatus?->id,
                    'milestone_id' => isset($t['milestone_id']) && isset($milestoneMap[$t['milestone_id']])
                        ? $milestoneMap[$t['milestone_id']]
                        : null,
                    'sprint_id' => isset($t['sprint_id']) && isset($sprintMap[$t['sprint_id']])
                        ? $sprintMap[$t['sprint_id']]
                        : null,
                ]);
                $tasksCreated++;
            }

            return $tasksCreated;
        });
    }

    private function importCsv(ImportJob $importJob, string $content): int
    {
        $project = $importJob->project;
        $userId = $importJob->user_id;

        return DB::transaction(function () use ($content, $project, $userId) {
            $lines = array_filter(explode("\n", trim($content)));
            $header = null;
            $tasksCreated = 0;

            $defaultStatus = $project->statuses()->where('is_default', true)->first()
                ?? $project->statuses()->first();

            $statusCache = [];
            $milestoneCache = [];
            $sprintCache = [];

            foreach ($lines as $line) {
                $row = str_getcsv($line);

                if ($header === null) {
                    $header = $row;

                    continue;
                }

                $data = array_combine($header, $row);

                // Resolve status by name
                $statusName = $data['status'] ?? null;
                if ($statusName && ! isset($statusCache[$statusName])) {
                    $statusCache[$statusName] = $project->statuses()
                        ->where('name', $statusName)
                        ->value('id');
                }
                $statusId = $statusCache[$statusName] ?? $defaultStatus?->id;

                // Resolve milestone by name
                $milestoneName = $data['milestone'] ?? null;
                if ($milestoneName && ! isset($milestoneCache[$milestoneName])) {
                    $milestoneCache[$milestoneName] = $project->milestones()
                        ->where('name', $milestoneName)
                        ->value('id');
                }
                $milestoneId = $milestoneCache[$milestoneName] ?? null;

                // Resolve sprint by name
                $sprintName = $data['sprint'] ?? null;
                if ($sprintName && ! isset($sprintCache[$sprintName])) {
                    $sprintCache[$sprintName] = $project->sprints()
                        ->where('name', $sprintName)
                        ->value('id');
                }
                $sprintId = $sprintCache[$sprintName] ?? null;

                $title = $data['title'] ?? null;
                if (! $title) {
                    continue;
                }

                Task::create([
                    'project_id' => $project->id,
                    'created_by' => $userId,
                    'title' => $title,
                    'description' => $data['description'] ?: null,
                    'status' => $data['status'] ?: 'todo',
                    'priority' => $data['priority'] ?: 'medium',
                    'position' => 0,
                    'due_date' => $data['due_date'] ?: null,
                    'started_at' => $data['started_at'] ?: null,
                    'estimate' => is_numeric($data['estimate'] ?? '') ? (int) $data['estimate'] : null,
                    'project_status_id' => $statusId,
                    'milestone_id' => $milestoneId,
                    'sprint_id' => $sprintId,
                ]);
                $tasksCreated++;
            }

            return $tasksCreated;
        });
    }

    private function notifyUser(ImportJob $importJob, int $tasksCreated): void
    {
        $notification = Notification::create([
            'user_id' => $importJob->user_id,
            'type' => 'ImportCompleted',
            'data' => [
                'import_job_id' => $importJob->id,
                'project_id' => $importJob->project_id,
                'project_name' => $importJob->project->name,
                'tasks_created' => $tasksCreated,
            ],
        ]);
        broadcast(new NotificationCreated($notification));
    }
}
