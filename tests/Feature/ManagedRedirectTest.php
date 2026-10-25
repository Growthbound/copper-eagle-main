<?php

namespace Tests\Feature;

use App\Content\RedirectValidator;
use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BindsRegistryFixtures;
use Tests\TestCase;

class ManagedRedirectTest extends TestCase
{
    use BindsRegistryFixtures;
    use RefreshDatabase;

    public function test_enabled_redirect_is_one_hop_and_preserves_the_request_query(): void
    {
        $user = User::factory()->create();
        RedirectRule::create([
            'source_path' => '/legacy/service',
            'target' => '/our-services/example-service',
            'status_code' => 301,
            'enabled' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->get('/legacy/service?utm_source=mail&campaign=summer')
            ->assertStatus(301);
        $location = $response->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->assertStringEndsWith('/our-services/example-service', (string) parse_url((string) $location, PHP_URL_PATH));
        $this->assertSame([
            'campaign' => 'summer',
            'utm_source' => 'mail',
        ], collect($query)->sortKeys()->all());
    }

    public function test_disabled_redirect_does_not_change_normal_route_resolution(): void
    {
        $user = User::factory()->create();
        RedirectRule::create([
            'source_path' => '/legacy/disabled',
            'target' => '/our-services/example-service',
            'enabled' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->get('/legacy/disabled')->assertNotFound();
    }

    public function test_absolute_https_target_does_not_receive_the_request_query(): void
    {
        $user = User::factory()->create();
        RedirectRule::create([
            'source_path' => '/legacy/external',
            'target' => 'https://example.com/final',
            'status_code' => 302,
            'enabled' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->get('/legacy/external?next=https%3A%2F%2Fevil.example')
            ->assertStatus(302)
            ->assertRedirect('https://example.com/final');
    }

    public function test_redirect_validator_rejects_unsafe_sources_targets_self_targets_and_statuses(): void
    {
        $validator = app(RedirectValidator::class);

        foreach ([
            ['/admin/content', '/services'],
            ['/api/content', '/services'],
            ['/_templates/faq', '/services'],
            ['/templates', '/services'],
            ['/images/home', '/services'],
            ['/legacy', 'http://evil.example/steal'],
            ['/legacy', 'javascript:alert(1)'],
            ['/legacy', '//evil.example/steal'],
            ['/legacy', '/legacy'],
        ] as [$source, $target]) {
            $this->assertTrue($validator->validate([
                'source_path' => $source,
                'target' => $target,
                'status_code' => 301,
                'enabled' => true,
            ])->hasErrors(), "Expected {$source} -> {$target} to fail.");
        }

        $this->assertArrayHasKey('/status_code', $validator->validate([
            'source_path' => '/legacy',
            'target' => '/services',
            'status_code' => 200,
            'enabled' => true,
        ])->errors());
    }

    /**
     * The chain checks query this table only, so a rule aimed at a URL the
     * intent ledger has already folded would build the same two-hop chain
     * across the two systems that serve redirects.
     */
    public function test_redirect_validator_rejects_a_target_the_intent_ledger_has_folded(): void
    {
        // The fixture ledger folds this duplicate into its pillar.
        $this->useLedgerFixture();

        $errors = app(RedirectValidator::class)->validate([
            'source_path' => '/legacy/old-service',
            'target' => '/services/ledger-fixture-duplicate',
            'status_code' => 301,
            'enabled' => true,
        ])->errors();

        $this->assertArrayHasKey('/target', $errors);
        $this->assertStringContainsString(
            '/services/ledger-fixture-pillar/',
            implode(' ', $errors['/target']),
        );
    }

    public function test_redirect_validator_rejects_chains_and_loops_in_either_direction(): void
    {
        $user = User::factory()->create();
        RedirectRule::create([
            'source_path' => '/legacy/first',
            'target' => '/services/final',
            'enabled' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $validator = app(RedirectValidator::class);

        $toManagedSource = $validator->validate([
            'source_path' => '/legacy/other',
            'target' => '/legacy/first',
            'status_code' => 301,
            'enabled' => true,
        ]);
        $fromManagedTarget = $validator->validate([
            'source_path' => '/services/final',
            'target' => '/legacy/other',
            'status_code' => 301,
            'enabled' => true,
        ]);
        $loop = $validator->validate([
            'source_path' => '/services/final',
            'target' => '/legacy/first',
            'status_code' => 301,
            'enabled' => true,
        ]);

        $this->assertArrayHasKey('/target', $toManagedSource->errors());
        $this->assertArrayHasKey('/source_path', $fromManagedTarget->errors());
        $this->assertTrue($loop->hasErrors());
    }

    public function test_same_origin_absolute_targets_participate_in_loop_and_chain_checks(): void
    {
        config(['app.url' => 'https://example.test']);

        $user = User::factory()->create();
        RedirectRule::create([
            'source_path' => '/legacy/first',
            'target' => 'https://example.test/services/final',
            'enabled' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $validator = app(RedirectValidator::class);

        $self = $validator->validate([
            'source_path' => '/legacy/self',
            'target' => 'https://example.test/legacy/self?campaign=summer',
            'status_code' => 301,
            'enabled' => true,
        ]);
        $toManagedSource = $validator->validate([
            'source_path' => '/legacy/other',
            'target' => 'https://example.test/legacy/first',
            'status_code' => 301,
            'enabled' => true,
        ]);
        $fromManagedTarget = $validator->validate([
            'source_path' => '/services/final',
            'target' => '/legacy/other',
            'status_code' => 301,
            'enabled' => true,
        ]);

        $this->assertArrayHasKey('/target', $self->errors());
        $this->assertArrayHasKey('/target', $toManagedSource->errors());
        $this->assertArrayHasKey('/source_path', $fromManagedTarget->errors());
    }
}
