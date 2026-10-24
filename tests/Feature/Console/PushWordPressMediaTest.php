<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushWordPressMediaTest extends TestCase
{
    public function test_missing_credentials_fail_before_network_access_without_exposing_a_secret(): void
    {
        config([
            'media-import.api_url' => null,
            'media-import.api_user' => null,
            'media-import.api_key' => 'do-not-print-this-secret-fragment',
        ]);
        Http::fake();

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->expectsOutputToContain('credentials')
            ->doesntExpectOutputToContain('do-not-print-this-secret-fragment')
            ->assertExitCode(Command::FAILURE);

        Http::assertNothingSent();
    }

    public function test_two_wordpress_pages_are_requested_once_each_in_order(): void
    {
        $this->configureImporter();
        $requestedPages = [];

        Http::fake(function (Request $request) use (&$requestedPages) {
            if ($request->method() === 'GET') {
                $requestedPages[] = (int) $request['page'];

                return Http::response(
                    [$this->wordpressItem($requestedPages[count($requestedPages) - 1])],
                    200,
                    ['X-WP-TotalPages' => '2'],
                );
            }

            return Http::response($this->ledger(count($request->data()['items'])), 200);
        });

        $this->artisan('media:push-wordpress', ['site' => 'https://example.com/an-ignored-path'])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame([1, 2], $requestedPages);
        Http::assertSentCount(3);
    }

    public function test_non_image_and_non_mp4_records_are_skipped(): void
    {
        Http::fake([
            '*' => Http::response([
                $this->wordpressItem(1, 'image/jpeg'),
                $this->wordpressItem(2, 'application/pdf'),
                $this->wordpressItem(3, 'image/svg+xml'),
                $this->wordpressItem(4, 'video/mp4'),
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $this->artisan('media:push-wordpress', ['site' => 'example.com', '--dry-run' => true])
            ->expectsOutputToContain('Requested: 2')
            ->expectsOutputToContain('Skipped: 2')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_twenty_six_eligible_records_are_sent_in_batches_of_twenty_five_and_one(): void
    {
        $this->configureImporter();
        $items = array_map(fn (int $index): array => $this->wordpressItem($index), range(1, 26));
        $batchSizes = [];

        Http::fake(function (Request $request) use ($items, &$batchSizes) {
            if ($request->method() === 'GET') {
                return Http::response($items, 200, ['X-WP-TotalPages' => '1']);
            }

            $batchSizes[] = count($request->data()['items']);

            return Http::response($this->ledger(end($batchSizes)), 200);
        });

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->expectsOutputToContain('Batches: 2')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame([25, 1], $batchSizes);
    }

    public function test_import_requests_use_basic_auth_and_the_exact_configured_endpoint(): void
    {
        $this->configureImporter();
        Http::fake([
            'https://example.com/wp-json/wp/v2/media*' => Http::response([
                $this->wordpressItem(1),
            ], 200, ['X-WP-TotalPages' => '1']),
            'https://destination.test/api/media/import' => Http::response($this->ledger(1), 200),
        ]);

        $this->artisan('media:push-wordpress', ['site' => 'https://example.com'])
            ->assertExitCode(Command::SUCCESS);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://destination.test/api/media/import'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('owner@example.test:local-test-key'))
                && $request->hasHeader('Idempotency-Key')
                && str_starts_with($request->header('Idempotency-Key')[0], 'media-import-');
        });
    }

    public function test_titles_are_decoded_and_blank_optional_text_becomes_null(): void
    {
        $this->configureImporter();
        $item = $this->wordpressItem(1);
        $item['title']['rendered'] = '  Kitchen &amp; Bath &quot;Team&quot;  ';
        $item['alt_text'] = '   ';
        $blankItem = $this->wordpressItem(2);
        $blankItem['title']['rendered'] = '   ';

        Http::fake(function (Request $request) use ($item, $blankItem) {
            if ($request->method() === 'GET') {
                return Http::response([$item, $blankItem], 200, ['X-WP-TotalPages' => '1']);
            }

            $imports = $request->data()['items'];
            $this->assertSame('Kitchen & Bath "Team"', $imports[0]['title']);
            $this->assertNull($imports[0]['alt']);
            $this->assertArrayNotHasKey('handle', $imports[0]);
            $this->assertNull($imports[1]['title']);

            return Http::response($this->ledger(2), 200);
        });

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_dry_run_sends_no_import_request_and_reports_truthful_totals(): void
    {
        config([
            'media-import.api_url' => null,
            'media-import.api_user' => null,
            'media-import.api_key' => null,
        ]);
        Http::fake([
            '*' => Http::response([
                $this->wordpressItem(1),
                $this->wordpressItem(2, 'video/mp4'),
                $this->wordpressItem(3, 'application/pdf'),
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $this->artisan('media:push-wordpress', ['site' => 'example.com', '--dry-run' => true])
            ->expectsOutputToContain('Requested: 2')
            ->expectsOutputToContain('Created: 0')
            ->expectsOutputToContain('Existing: 0')
            ->expectsOutputToContain('Failed: 0')
            ->expectsOutputToContain('Skipped: 1')
            ->expectsOutputToContain('Batches: 1')
            ->assertExitCode(Command::SUCCESS);

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_api_item_failure_returns_failure_after_reporting_aggregate_totals(): void
    {
        $this->configureImporter();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    $this->wordpressItem(1),
                    $this->wordpressItem(2),
                ], 200, ['X-WP-TotalPages' => '1']);
            }

            return Http::response($this->ledger(2, created: 1, failed: 1), 200);
        });

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->expectsOutputToContain('Requested: 2')
            ->expectsOutputToContain('Created: 1')
            ->expectsOutputToContain('Existing: 0')
            ->expectsOutputToContain('Failed: 1')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_irreconcilable_api_counts_fail_the_command(): void
    {
        $this->configureImporter();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([$this->wordpressItem(1)], 200, ['X-WP-TotalPages' => '1']);
            }

            return Http::response($this->ledger(1, created: 0, existing: 0, failed: 0), 200);
        });

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->expectsOutputToContain('invalid import ledger')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_missing_api_ledger_fails_the_command(): void
    {
        $this->configureImporter();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([$this->wordpressItem(1)], 200, ['X-WP-TotalPages' => '1']);
            }

            return Http::response(['success' => true], 200);
        });

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->expectsOutputToContain('invalid import ledger')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_http_failures_return_failure_without_printing_credentials(): void
    {
        config([
            'media-import.api_url' => 'https://destination.test/api/media/import',
            'media-import.api_user' => 'owner@example.test',
            'media-import.api_key' => 'never-print-this-http-secret',
        ]);
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([$this->wordpressItem(1)], 200, ['X-WP-TotalPages' => '1']);
            }

            return Http::response('upstream unavailable', 503);
        });

        $this->artisan('media:push-wordpress', ['site' => 'example.com'])
            ->expectsOutputToContain('request failed')
            ->doesntExpectOutputToContain('never-print-this-http-secret')
            ->assertExitCode(Command::FAILURE);
    }

    private function configureImporter(): void
    {
        config([
            'media-import.api_url' => 'https://destination.test/api/media/import',
            'media-import.api_user' => 'owner@example.test',
            'media-import.api_key' => 'local-test-key',
            'media-import.max_items' => 25,
        ]);
    }

    /** @return array<string, mixed> */
    private function wordpressItem(int $index, string $mimeType = 'image/jpeg'): array
    {
        return [
            'id' => $index,
            'source_url' => "https://cdn.example.test/media-{$index}.jpg",
            'mime_type' => $mimeType,
            'title' => ['rendered' => "Media {$index}"],
            'alt_text' => "Alt {$index}",
        ];
    }

    /** @return array<string, mixed> */
    private function ledger(
        int $requested,
        ?int $created = null,
        int $existing = 0,
        int $failed = 0,
    ): array {
        $created ??= $requested - $existing - $failed;
        $items = [
            ...array_fill(0, $created, ['status' => 'created']),
            ...array_fill(0, $existing, ['status' => 'existing']),
            ...array_fill(0, $failed, ['status' => 'failed']),
        ];

        return [
            'summary' => compact('requested', 'created', 'existing', 'failed'),
            'items' => $items,
        ];
    }
}
