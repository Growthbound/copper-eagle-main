<?php

namespace Tests\Feature\Api;

use App\Content\Enums\ApiKeyScope;
use App\Http\Middleware\ThrottleApiPeer;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\Api\Concerns\InteractsWithContentApi;
use Tests\TestCase;

class ContentSchemaApiTest extends TestCase
{
    use InteractsWithContentApi;
    use RefreshDatabase;

    public function test_form_destinations_are_discoverable_without_exposing_provider_configuration(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentRead]);
        $blocks = $this->contentGet('/api/content/v1/schema')->assertOk()->json('data.blocks');
        foreach (['cta/quote-form', 'cta/quote-form-split'] as $type) {
            $block = collect($blocks)->firstWhere('type', $type);
            $this->assertSame(['inquiry', 'feedback', 'comments'], $block['fields']['purpose']['allowed']);
            $this->assertArrayNotHasKey('formId', $block['fields']);
        }
    }

    public function test_schema_requires_basic_authentication_and_read_scope(): void
    {
        $this->withHeader('X-Request-ID', 'unauthenticated-schema-001')
            ->getJson('/api/content/v1/schema')
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID', 'unauthenticated-schema-001')
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('request_id', 'unauthenticated-schema-001')
            ->assertJsonStructure(['message', 'code', 'request_id']);

        $this->authenticateContentApi([ApiKeyScope::MediaRead]);

        $this->contentGet('/api/content/v1/schema')
            ->assertForbidden()
            ->assertJsonPath('code', 'missing_scope')
            ->assertJsonStructure(['message', 'code', 'request_id']);
    }

    public function test_exception_rendering_preserves_the_request_correlation_envelope(): void
    {
        Exceptions::fake();

        Route::get('/api/content/v1/request-id-failure', function (): never {
            throw new RuntimeException('unexpected content failure');
        })->middleware('content.request-id');

        $this->withHeader('X-Request-ID', 'exception-request-20260726')
            ->getJson('/api/content/v1/request-id-failure')
            ->assertStatus(500)
            ->assertHeader('X-Request-ID', 'exception-request-20260726')
            ->assertJsonPath('code', 'request_failed')
            ->assertJsonPath('request_id', 'exception-request-20260726');

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_schema_is_secret_free_versioned_and_correlated(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentRead]);

        $response = $this->contentGet('/api/content/v1/schema', [
            'X-Request-ID' => 'hermes-schema-20260725',
        ])->assertOk()
            ->assertHeader('X-Request-ID', 'hermes-schema-20260725')
            ->assertJsonPath('data.schema_version', 1)
            ->assertJsonStructure([
                'data' => [
                    'blocks',
                    'page_templates',
                    'rich_content',
                    'enums' => ['content_kinds', 'content_statuses', 'api_key_scopes'],
                ],
                'meta' => ['request_id'],
            ]);

        $this->assertStringNotContainsString('secret_hash', $response->getContent());
        $this->assertStringNotContainsString('token_hash', $response->getContent());
    }

    public function test_every_content_route_has_authentication_rate_limit_and_an_explicit_scope(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/content/v1'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('api-key.resolve', $middleware, $route->uri());
            $this->assertContains('api-key.auth', $middleware, $route->uri());
            $this->assertContains('api-peer-throttle:content-api,120', $middleware, $route->uri());
            $this->assertContains('throttle:content-api', $middleware, $route->uri());
            $this->assertLessThan(
                array_search('api-key.resolve', $middleware, true),
                array_search('api-peer-throttle:content-api,120', $middleware, true),
                "The peer throttle must run before credential resolution for {$route->uri()}.",
            );
            $this->assertLessThan(
                array_search('throttle:content-api', $middleware, true),
                array_search('api-key.resolve', $middleware, true),
                "Credential resolution must precede the throttle for {$route->uri()}.",
            );
            $this->assertLessThan(
                array_search('api-key.auth', $middleware, true),
                array_search('throttle:content-api', $middleware, true),
                "The throttle must wrap authentication for {$route->uri()}.",
            );
            $this->assertTrue(
                collect($middleware)->contains(
                    fn (string $name): bool => str_starts_with($name, 'api-key.scope:'),
                ),
                $route->uri(),
            );
        }
    }

    public function test_exhausted_peer_bucket_blocks_before_api_key_resolution(): void
    {
        $request = Request::create('/api/content/v1/schema', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.201',
        ]);
        $limiterKey = ThrottleApiPeer::key('content-api', $request);

        for ($attempt = 1; $attempt <= 120; $attempt++) {
            RateLimiter::hit($limiterKey, 60);
        }

        $apiKeyQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$apiKeyQueries): void {
            if (str_contains(strtolower($query->sql), 'api_keys')) {
                $apiKeyQueries++;
            }
        });

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.201'])
            ->withBasicAuth('owner@example.com', 'site_live_valid_shape_12345678')
            ->getJson('/api/content/v1/schema')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');

        $this->assertSame(0, $apiKeyQueries);
    }

    public function test_peer_throttle_ignores_forwarded_addresses(): void
    {
        $first = Request::create('/api/content/v1/schema', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.202',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        ]);
        $second = Request::create('/api/content/v1/schema', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.202',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);

        $this->assertSame(
            ThrottleApiPeer::key('content-api', $first),
            ThrottleApiPeer::key('content-api', $second),
        );
    }

    public function test_rate_limit_failures_keep_the_stable_error_envelope(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentRead]);

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->contentGet('/api/content/v1/schema', [
                'X-Forwarded-For' => "203.0.113.{$attempt}",
            ])->assertOk();
        }

        $this->contentGet('/api/content/v1/schema', [
            'X-Forwarded-For' => '198.51.100.250',
        ])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonStructure(['message', 'code', 'request_id']);
    }

    public function test_forwarded_headers_cannot_create_new_unauthenticated_rate_limit_buckets(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->withHeaders([
                'X-Forwarded-For' => "203.0.113.{$attempt}",
            ])->getJson('/api/content/v1/schema')->assertUnauthorized();
        }

        $this->withHeaders([
            'X-Forwarded-For' => '198.51.100.250',
        ])->getJson('/api/content/v1/schema')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_changing_failed_basic_auth_guesses_cannot_create_new_rate_limit_buckets(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
                ->withBasicAuth('owner@example.com', "wrong-password-{$attempt}")
                ->getJson('/api/content/v1/schema')
                ->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->withBasicAuth('different@example.com', 'another-wrong-password')
            ->getJson('/api/content/v1/schema')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_failed_peer_bucket_cannot_throttle_a_valid_api_key_from_the_same_peer(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
                ->withBasicAuth('owner@example.com', "wrong-password-{$attempt}")
                ->getJson('/api/content/v1/schema')
                ->assertUnauthorized();
        }

        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $owner,
            'Valid peer-isolated key',
            [ApiKeyScope::ContentRead],
        );

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
            ->withBasicAuth(
                $owner->email,
                $issued->plaintext,
            )
            ->getJson('/api/content/v1/schema')
            ->assertOk();
    }

    public function test_rate_limited_valid_requests_do_not_write_key_usage(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $owner,
            'Write-bounded key',
            [ApiKeyScope::ContentRead],
        );

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->getJson('/api/content/v1/schema')
            ->assertOk();
        $limiterKey = md5('content-apiapi-key:'.$issued->key->getKey());

        for ($attempt = 2; $attempt <= 60; $attempt++) {
            RateLimiter::hit($limiterKey, 60);
        }

        $sentinel = now()->subDay()->startOfSecond();
        $issued->key->forceFill(['last_used_at' => $sentinel])->save();

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->getJson('/api/content/v1/schema')
            ->assertTooManyRequests();

        $this->assertTrue($issued->key->fresh()->last_used_at->equalTo($sentinel));
    }
}
