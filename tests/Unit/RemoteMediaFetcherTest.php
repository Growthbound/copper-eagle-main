<?php

namespace Tests\Unit;

use App\Services\Media\RemoteMediaFetcher;
use App\Services\Media\RemoteMediaFetchFailed;
use App\Services\Media\RemoteMediaTooLarge;
use App\Services\Media\RemoteUrlGuard;
use App\Services\Media\UnsafeRemoteUrl;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RemoteMediaFetcherTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_fetches_with_bounded_timeouts_and_automatic_redirects_disabled(): void
    {
        $history = [];
        $fetcher = $this->fetcher(
            [new Response(200, [], 'image bytes')],
            $history,
        );

        $file = $fetcher->fetch('https://media.example/image.jpg');
        $this->temporaryFiles[] = $file->getPathname();

        $this->assertSame('image bytes', file_get_contents($file->getPathname()));
        $this->assertSame(5, $history[0]['options']['connect_timeout']);
        $this->assertSame(30, $history[0]['options']['timeout']);
        $this->assertFalse($history[0]['options']['allow_redirects']);
    }

    public function test_pins_the_request_to_the_validated_ip_so_transport_dns_cannot_rebind(): void
    {
        $history = [];
        $resolverCalls = 0;
        $fetcher = $this->fetcher(
            [new Response(200, [], 'image bytes')],
            $history,
            resolver: function (string $host) use (&$resolverCalls): array {
                $resolverCalls++;

                return $resolverCalls === 1
                    ? ['93.184.216.34']
                    : ['10.0.0.4'];
            },
        );

        $file = $fetcher->fetch('https://media.example/image.jpg');
        $this->temporaryFiles[] = $file->getPathname();

        $this->assertSame(1, $resolverCalls);
        $this->assertSame(
            ['media.example:443:93.184.216.34'],
            $history[0]['options']['curl'][CURLOPT_RESOLVE] ?? null,
        );
        $this->assertSame('media.example', $history[0]['request']->getUri()->getHost());
    }

    public function test_follows_at_most_three_redirects_and_resolves_relative_locations(): void
    {
        $history = [];
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => '/one']),
            new Response(301, ['Location' => 'https://cdn.example/two']),
            new Response(307, ['Location' => '/three']),
            new Response(200, [], 'final bytes'),
        ], $history);

        $file = $fetcher->fetch('https://media.example/start');
        $this->temporaryFiles[] = $file->getPathname();

        $this->assertCount(4, $history);
        $this->assertSame('https://media.example/one', (string) $history[1]['request']->getUri());
        $this->assertSame('https://cdn.example/two', (string) $history[2]['request']->getUri());
        $this->assertSame('https://cdn.example/three', (string) $history[3]['request']->getUri());
        $this->assertSame('final bytes', file_get_contents($file->getPathname()));
    }

    public function test_rejects_a_private_redirect_before_requesting_it_and_removes_partial_file(): void
    {
        $history = [];
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => 'http://10.0.0.4/private.jpg']),
        ], $history);

        try {
            $fetcher->fetch('https://media.example/start');
            $this->fail('Expected the private redirect to be rejected.');
        } catch (UnsafeRemoteUrl) {
            $this->assertCount(1, $history);
            $this->assertSame([], $this->importTemporaryFiles());
        }
    }

    public function test_rejects_a_fourth_redirect_and_removes_partial_file(): void
    {
        $history = [];
        $fetcher = $this->fetcher([
            new Response(302, ['Location' => '/one']),
            new Response(302, ['Location' => '/two']),
            new Response(302, ['Location' => '/three']),
            new Response(302, ['Location' => '/four']),
        ], $history);

        try {
            $fetcher->fetch('https://media.example/start');
            $this->fail('Expected the redirect limit to be enforced.');
        } catch (RemoteMediaFetchFailed $exception) {
            $this->assertSame('too_many_redirects', $exception->errorCode);
            $this->assertCount(4, $history);
            $this->assertSame([], $this->importTemporaryFiles());
        }
    }

    public function test_rejects_an_oversize_response_and_removes_partial_file(): void
    {
        $history = [];
        $fetcher = $this->fetcher([
            new Response(200, ['Content-Length' => '32'], str_repeat('x', 32)),
        ], $history, maxBytes: 16);

        try {
            $fetcher->fetch('https://media.example/large.jpg');
            $this->fail('Expected the byte limit to be enforced.');
        } catch (RemoteMediaTooLarge $exception) {
            $this->assertSame('too_large', $exception->errorCode);
            $this->assertSame([], $this->importTemporaryFiles());
        }
    }

    /**
     * @param  array<int, Response>  $responses
     * @param  array<int, array<string, mixed>>  $history
     */
    private function fetcher(
        array $responses,
        array &$history,
        int $maxBytes = 1024,
        ?callable $resolver = null,
    ): RemoteMediaFetcher {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client(['handler' => $stack]);
        $guard = new RemoteUrlGuard($resolver ?? fn (string $host): array => ['93.184.216.34']);

        return new RemoteMediaFetcher(
            guard: $guard,
            client: $client,
            maxBytes: $maxBytes,
            connectTimeout: 5,
            timeout: 30,
            maxRedirects: 3,
        );
    }

    /**
     * @return array<int, string>
     */
    private function importTemporaryFiles(): array
    {
        $directory = storage_path('app/private/media-imports');

        if (! is_dir($directory)) {
            return [];
        }

        return File::files($directory);
    }
}
