<?php

namespace Tests\Unit;

use App\Content\UrlIntentLedger;
use PHPUnit\Framework\TestCase;

class UrlIntentLedgerTest extends TestCase
{
    public function test_it_accepts_a_ledger_with_one_owner_per_intent_and_one_disposition_per_url(): void
    {
        $ledger = $this->ledger(
            intents: [
                $this->intent('service.example-service', 'resolved'),
            ],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/services/example-service/', 'service.example-service', 'supporting', 'redirect', target: '/our-services/example-service/'),
            ],
        );

        $this->assertSame([], (new UrlIntentLedger)->validate($ledger));
    }

    public function test_it_rejects_a_forwarding_entry_that_is_still_indexable(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry(
                    '/services/example-service/',
                    'service.example-service',
                    'supporting',
                    'consolidate',
                    target: '/our-services/example-service/',
                    indexation: 'indexable',
                ),
            ],
        );

        $this->assertStringContainsString(
            'is a consolidate and cannot also be indexable',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_a_duplicate_url(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/our-services/example-service/', 'service.example-service', 'supporting', 'retire'),
            ],
        );

        $this->assertStringContainsString(
            'appears more than once',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_two_primary_owners_for_one_intent(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
            ],
        );

        $this->assertStringContainsString(
            'has 2 primary owners',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_requires_a_resolved_intent_to_have_an_owner(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/services/example-service/', 'service.example-service', 'contested', 'unresolved'),
            ],
        );

        $errors = implode("\n", (new UrlIntentLedger)->validate($ledger));

        $this->assertStringContainsString('is resolved but has no primary owner', $errors);
    }

    public function test_it_rejects_a_contested_entry_on_a_resolved_intent(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/services/example-service/', 'service.example-service', 'contested', 'unresolved'),
            ],
        );

        $this->assertStringContainsString(
            'is contested but its intent',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_an_unknown_intent_reference(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/our-services/mystery/', 'service.unknown', 'supporting', 'preserve'),
            ],
        );

        $this->assertStringContainsString(
            'references unknown intent',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_an_unknown_disposition(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'delete-it'),
            ],
        );

        $this->assertStringContainsString(
            'has unknown disposition',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_requires_a_target_for_redirect_and_consolidate_dispositions(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/services/example-service/', 'service.example-service', 'supporting', 'redirect'),
            ],
        );

        $this->assertStringContainsString(
            'requires a disposition_target',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_a_target_on_a_terminal_disposition(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve', target: '/somewhere/'),
            ],
        );

        $this->assertStringContainsString(
            'must not set a disposition_target',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_a_redirect_to_an_unlisted_url(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/services/example-service/', 'service.example-service', 'supporting', 'redirect', target: '/nowhere/'),
            ],
        );

        $this->assertStringContainsString(
            'targets /nowhere/, which is not in the ledger',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    public function test_it_rejects_redirect_chains_and_self_redirects(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
                $this->entry('/a/', 'service.example-service', 'supporting', 'redirect', target: '/b/'),
                $this->entry('/b/', 'service.example-service', 'supporting', 'redirect', target: '/our-services/example-service/'),
                $this->entry('/c/', 'service.example-service', 'supporting', 'redirect', target: '/c/'),
            ],
        );

        $errors = implode("\n", (new UrlIntentLedger)->validate($ledger));

        $this->assertStringContainsString('creates a redirect chain', $errors);
        $this->assertStringContainsString('redirects to itself', $errors);
    }

    public function test_it_rejects_an_evidence_gate_that_is_not_a_claims_register_key(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry(
                    '/our-services/example-service/',
                    'service.example-service',
                    'primary_owner',
                    'preserve',
                    gate: 'claims.invented',
                ),
            ],
        );

        $this->assertStringContainsString(
            'references unknown claim key claims.invented',
            implode("\n", (new UrlIntentLedger)->validate($ledger, ['entity.primary'])),
        );
    }

    public function test_it_rejects_a_declared_defect_without_a_matching_entry(): void
    {
        $ledger = $this->ledger(
            intents: [$this->intent('service.example-service', 'resolved')],
            entries: [
                $this->entry('/our-services/example-service/', 'service.example-service', 'primary_owner', 'preserve'),
            ],
            defects: [
                ['id' => 'indexable_without_evidence', 'url' => '/ghost/', 'detail' => 'not a real url'],
            ],
        );

        $this->assertStringContainsString(
            'declares a defect for /ghost/, which is not in the ledger',
            implode("\n", (new UrlIntentLedger)->validate($ledger)),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $intents
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array<string, string>>  $defects
     * @return array<string, mixed>
     */
    private function ledger(array $intents, array $entries, array $defects = []): array
    {
        return [
            'schema_version' => 1,
            'reviewed_on' => '2026-08-01',
            'intents' => $intents,
            'entries' => $entries,
            'known_defects' => $defects,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function intent(string $id, string $status): array
    {
        return ['id' => $id, 'label' => 'Example intent', 'status' => $status];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(
        string $url,
        string $intent,
        string $role,
        string $disposition,
        ?string $target = null,
        ?string $gate = null,
        ?string $indexation = null,
    ): array {
        return [
            'url' => $url,
            'source' => 'managed',
            'content_kind' => 'service',
            'intent' => $intent,
            'intent_role' => $role,
            // A forwarding entry serves a redirect rather than a page, so the
            // default follows the disposition instead of contradicting it.
            'indexation' => $indexation ?? (in_array($disposition, ['consolidate', 'redirect'], true)
                ? 'noindex'
                : 'indexable'),
            'canonical' => $url,
            'disposition' => $disposition,
            'disposition_target' => $target,
            'evidence_gates' => $gate === null ? [] : [$gate],
            'notes' => 'fixture',
        ];
    }
}
