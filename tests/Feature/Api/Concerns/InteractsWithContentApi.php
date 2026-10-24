<?php

namespace Tests\Feature\Api\Concerns;

use App\Content\Enums\ApiKeyScope;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Testing\TestResponse;

trait InteractsWithContentApi
{
    /** @var array{email: string, plaintext: string}|null */
    private ?array $contentApiCredentials = null;

    /**
     * @param  array<int, ApiKeyScope>  $scopes
     */
    protected function authenticateContentApi(array $scopes): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Hermes test key', $scopes);

        $this->contentApiCredentials = [
            'email' => $owner->email,
            'plaintext' => $issued->plaintext,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function contentGet(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers)
            ->withBasicAuth(
                $this->contentApiCredentials['email'],
                $this->contentApiCredentials['plaintext'],
            )
            ->getJson($uri);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function contentPost(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withBasicAuth(
            $this->contentApiCredentials['email'],
            $this->contentApiCredentials['plaintext'],
        )->postJson($uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function contentDelete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withBasicAuth(
            $this->contentApiCredentials['email'],
            $this->contentApiCredentials['plaintext'],
        )->deleteJson($uri, $data, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    protected function standardEntryPayload(string $slug = 'about'): array
    {
        return [
            'identity' => [
                'kind' => 'page',
                'slug' => $slug,
                'title' => str($slug)->replace('-', ' ')->title()->toString(),
                'template' => 'standard',
            ],
            'payload' => [
                'title' => str($slug)->replace('-', ' ')->title()->toString(),
                'blocks' => [[
                    'type' => 'content/direct-answer',
                    'props' => [
                        'heading' => 'What should homeowners expect?',
                        'answer' => [[
                            'text' => 'A careful inspection gives your family a clear, practical starting point.',
                        ]],
                    ],
                ]],
            ],
        ];
    }

    protected function idempotencyHeaders(
        string $key,
        ?int $lockVersion = null,
        string $requestId = 'request-content-api-001',
    ): array {
        return array_filter([
            'Idempotency-Key' => $key,
            'If-Match' => $lockVersion === null ? null : '"'.$lockVersion.'"',
            'X-Request-ID' => $requestId,
        ], fn (?string $value): bool => $value !== null);
    }
}
