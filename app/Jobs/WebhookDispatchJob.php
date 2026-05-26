<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $workspaceId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $webhooks = Webhook::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $w) => in_array($this->event, $w->events, true));

        foreach ($webhooks as $webhook) {
            $this->deliver($webhook);
        }
    }

    private function deliver(Webhook $webhook): void
    {
        $body = json_encode($this->payload);
        $signature = hash_hmac('sha256', $body, $webhook->secret);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $this->event,
            'payload' => $this->payload,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-DevBoard-Signature' => "sha256={$signature}",
                    'X-DevBoard-Event' => $this->event,
                ])
                ->post($webhook->url, $this->payload);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'delivered_at' => now(),
            ]);
        } catch (Throwable $e) {
            $delivery->update([
                'response_body' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            // Re-throw so the job retries
            throw $e;
        }
    }
}
