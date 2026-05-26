<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const BASE = 'https://api.github.com';

    private function client(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->baseUrl(self::BASE);
    }

    public function getAuthenticatedUser(string $token): array
    {
        $response = $this->client($token)->get('/user');
        $response->throw();
        return $response->json();
    }

    public function getRepo(string $token, string $repo): array
    {
        $response = $this->client($token)->get("/repos/{$repo}");
        $response->throw();
        return $response->json();
    }

    public function listOpenIssues(string $token, string $repo, int $page = 1): array
    {
        $response = $this->client($token)->get("/repos/{$repo}/issues", [
            'state'    => 'open',
            'per_page' => 50,
            'page'     => $page,
        ]);
        $response->throw();
        return $response->json();
    }

    public function getIssue(string $token, string $repo, int $number): array
    {
        $response = $this->client($token)->get("/repos/{$repo}/issues/{$number}");
        $response->throw();
        return $response->json();
    }

    public function getPullRequest(string $token, string $repo, int $number): array
    {
        $response = $this->client($token)->get("/repos/{$repo}/pulls/{$number}");
        $response->throw();
        return $response->json();
    }

    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader, string $secret): bool
    {
        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $secret);
        return hash_equals($expected, $signatureHeader);
    }
}
