<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GitHubIntegration;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GitHubController extends Controller
{
    public function __construct(private GitHubService $github) {}

    // GET /workspaces/{workspace}/github
    public function status(Request $request, Workspace $workspace): JsonResponse
    {
        $this->wsGate($workspace, ['owner', 'admin', 'member', 'guest']);

        $integration = $workspace->githubIntegration;
        if (! $integration) {
            return response()->json(['connected' => false]);
        }

        return response()->json([
            'connected' => true,
            'github_username' => $integration->github_username,
            'webhook_url' => url("/api/webhooks/github/{$workspace->id}"),
            'webhook_secret' => $integration->webhook_secret,
        ]);
    }

    // POST /workspaces/{workspace}/github
    public function connect(Request $request, Workspace $workspace): JsonResponse
    {
        $this->wsGate($workspace, ['owner', 'admin']);

        $data = $request->validate(['token' => 'required|string']);

        try {
            $ghUser = $this->github->getAuthenticatedUser($data['token']);
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid GitHub token or GitHub API unreachable.'], 422);
        }

        $integration = GitHubIntegration::updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'access_token' => $data['token'],
                'github_username' => $ghUser['login'],
                'webhook_secret' => Str::random(32),
            ]
        );

        return response()->json([
            'connected' => true,
            'github_username' => $integration->github_username,
            'webhook_url' => url("/api/webhooks/github/{$workspace->id}"),
            'webhook_secret' => $integration->webhook_secret,
        ], 201);
    }

    // DELETE /workspaces/{workspace}/github
    public function disconnect(Request $request, Workspace $workspace): JsonResponse
    {
        $this->wsGate($workspace, ['owner', 'admin']);

        $workspace->githubIntegration?->delete();

        return response()->json(null, 204);
    }

    // PATCH /workspaces/{workspace}/projects/{project}/github/repo
    public function linkRepo(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->wsGate($workspace, ['owner', 'admin', 'member']);
        abort_if($project->workspace_id !== $workspace->id, 404);

        $integration = $workspace->githubIntegration;
        abort_if(! $integration, 422, 'GitHub is not connected for this workspace.');

        $data = $request->validate(['github_repo' => 'nullable|string|regex:/^[^\/]+\/[^\/]+$/']);

        if (! empty($data['github_repo'])) {
            try {
                $this->github->getRepo($integration->decrypted_token, $data['github_repo']);
            } catch (\Throwable) {
                return response()->json(['message' => 'Repository not found or token has no access.'], 422);
            }
        }

        $project->update(['github_repo' => $data['github_repo'] ?? null]);

        return response()->json(['github_repo' => $project->github_repo]);
    }

    // GET /workspaces/{workspace}/projects/{project}/github/issues
    public function listIssues(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->projectGate($workspace, $project);

        $integration = $workspace->githubIntegration;
        abort_if(! $integration, 422, 'GitHub is not connected for this workspace.');
        abort_if(! $project->github_repo, 422, 'No GitHub repository linked to this project.');

        try {
            $issues = $this->github->listOpenIssues(
                $integration->decrypted_token,
                $project->github_repo,
                (int) $request->query('page', 1)
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch issues from GitHub.'], 502);
        }

        // Filter out PRs (GitHub issues endpoint returns both)
        $issues = array_values(array_filter($issues, fn ($i) => ! isset($i['pull_request'])));

        return response()->json(array_map(fn ($i) => [
            'number' => $i['number'],
            'title' => $i['title'],
            'state' => $i['state'],
            'url' => $i['html_url'],
            'labels' => array_map(fn ($l) => ['name' => $l['name'], 'color' => $l['color']], $i['labels'] ?? []),
        ], $issues));
    }

    // PATCH /tasks/{task}/github
    public function linkTask(Request $request, Task $task): JsonResponse
    {
        $this->taskGuestCheck($task);
        $workspace = $task->project->workspace;
        abort_if(! in_array($workspace->userRole($request->user()), ['owner', 'admin', 'member']), 403);

        $data = $request->validate([
            'github_issue_number' => 'nullable|integer|min:1',
            'github_pr_number' => 'nullable|integer|min:1',
            'github_pr_state' => 'nullable|string|in:open,closed,merged',
        ]);

        $task->update($data);

        return response()->json([
            'github_issue_number' => $task->github_issue_number,
            'github_pr_number' => $task->github_pr_number,
            'github_pr_state' => $task->github_pr_state,
        ]);
    }

    // POST /webhooks/github/{workspace}  (no auth:sanctum — public endpoint)
    public function webhook(Request $request, Workspace $workspace): JsonResponse
    {
        $integration = $workspace->githubIntegration;
        if (! $integration) {
            return response()->json(['message' => 'Not configured.'], 404);
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $rawPayload = $request->getContent();

        if (! $this->github->verifyWebhookSignature($rawPayload, $signature, $integration->webhook_secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->json()->all();

        match ($event) {
            'pull_request' => $this->handlePullRequest($payload),
            'issues' => $this->handleIssues($payload),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    private function handlePullRequest(array $payload): void
    {
        $action = $payload['action'] ?? '';
        $pr = $payload['pull_request'] ?? [];
        $number = (int) ($pr['number'] ?? 0);

        if (! $number) {
            return;
        }

        $state = match ($action) {
            'closed' => $pr['merged'] ? 'merged' : 'closed',
            'opened', 'reopened' => 'open',
            default => null,
        };

        if ($state === null) {
            return;
        }

        Task::where('github_pr_number', $number)->update(['github_pr_state' => $state]);
    }

    private function handleIssues(array $payload): void
    {
        $action = $payload['action'] ?? '';
        $number = (int) ($payload['issue']['number'] ?? 0);

        if (! $number) {
            return;
        }

        if ($action === 'closed') {
            Task::where('github_issue_number', $number)
                ->where('status', '!=', 'done')
                ->update(['status' => 'done']);
        }
    }
}
