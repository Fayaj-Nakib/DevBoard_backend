<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\WebhookDispatchJob;
use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    private const VALID_EVENTS = [
        'task.created', 'task.updated', 'task.deleted',
        'task.status_changed', 'comment.created',
        'project.created', 'project.updated',
    ];

    private function gate(Workspace $workspace): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_if(!in_array($workspace->userRole($user), ['owner', 'admin']), 403);
    }

    public function index(Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);

        $webhooks = $workspace->webhooks()
            ->orderBy('created_at')
            ->get()
            ->map(fn($w) => $this->formatWebhook($w));

        return response()->json($webhooks);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->gate($workspace);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'url'       => 'required|url|max:500',
            'secret'    => 'required|string|min:8|max:255',
            'events'    => 'required|array|min:1',
            'events.*'  => 'string|in:' . implode(',', self::VALID_EVENTS),
            'is_active' => 'boolean',
        ]);

        $webhook = $workspace->webhooks()->create($data);

        return response()->json($this->formatWebhook($webhook), 201);
    }

    public function show(Workspace $workspace, Webhook $webhook): JsonResponse
    {
        $this->gate($workspace);
        abort_if($webhook->workspace_id !== $workspace->id, 404);

        $deliveries = $webhook->deliveries()
            ->limit(20)
            ->get()
            ->map(fn($d) => [
                'id'              => $d->id,
                'event'           => $d->event,
                'response_status' => $d->response_status,
                'delivered_at'    => $d->delivered_at?->toIso8601String(),
                'failed_at'       => $d->failed_at?->toIso8601String(),
                'created_at'      => $d->created_at?->toIso8601String(),
            ]);

        return response()->json(array_merge(
            $this->formatWebhook($webhook),
            ['recent_deliveries' => $deliveries]
        ));
    }

    public function update(Request $request, Workspace $workspace, Webhook $webhook): JsonResponse
    {
        $this->gate($workspace);
        abort_if($webhook->workspace_id !== $workspace->id, 404);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'url'       => 'sometimes|url|max:500',
            'secret'    => 'sometimes|string|min:8|max:255',
            'events'    => 'sometimes|array|min:1',
            'events.*'  => 'string|in:' . implode(',', self::VALID_EVENTS),
            'is_active' => 'sometimes|boolean',
        ]);

        $webhook->update($data);

        return response()->json($this->formatWebhook($webhook->fresh()));
    }

    public function destroy(Workspace $workspace, Webhook $webhook): JsonResponse
    {
        $this->gate($workspace);
        abort_if($webhook->workspace_id !== $workspace->id, 404);

        $webhook->delete();

        return response()->json(null, 204);
    }

    public function test(Workspace $workspace, Webhook $webhook): JsonResponse
    {
        $this->gate($workspace);
        abort_if($webhook->workspace_id !== $workspace->id, 404);

        WebhookDispatchJob::dispatch($workspace->id, 'ping', [
            'event'        => 'ping',
            'workspace_id' => $workspace->id,
            'message'      => 'This is a test webhook delivery from DevBoard.',
            'timestamp'    => now()->toIso8601String(),
        ]);

        return response()->json(['message' => 'Test webhook queued.']);
    }

    private function formatWebhook(Webhook $webhook): array
    {
        return [
            'id'         => $webhook->id,
            'name'       => $webhook->name,
            'url'        => $webhook->url,
            'events'     => $webhook->events,
            'is_active'  => $webhook->is_active,
            'created_at' => $webhook->created_at?->toIso8601String(),
        ];
    }
}
