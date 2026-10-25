<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PushWordPressMedia extends Command
{
    /** @var list<string> */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/gif',
        'video/mp4',
    ];

    protected $signature = 'media:push-wordpress
                            {site : The WordPress site origin}
                            {--dry-run : Inventory media without sending import requests}';

    protected $description = 'Push supported WordPress media through the protected media import API';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $credentials = $this->credentials();

        if (! $dryRun && $credentials === null) {
            $this->components->error('Import API credentials are incomplete.');

            return self::FAILURE;
        }

        $origin = $this->normalizeOrigin((string) $this->argument('site'));

        if ($origin === null) {
            $this->components->error('The WordPress site must be a valid HTTP or HTTPS origin.');

            return self::FAILURE;
        }

        $summary = [
            'requested' => 0,
            'created' => 0,
            'existing' => 0,
            'failed' => 0,
            'skipped' => 0,
            'batches' => 0,
        ];

        try {
            [$items, $skipped] = $this->enumerateWordPressMedia($origin);
        } catch (Throwable) {
            $this->components->error('The WordPress media request failed.');
            $this->printSummary($summary);

            return self::FAILURE;
        }

        $maxItems = max(1, (int) config('media-import.max_items', 25));
        $batches = array_chunk($items, $maxItems);
        $summary['requested'] = count($items);
        $summary['skipped'] = $skipped;
        $summary['batches'] = count($batches);

        if ($dryRun) {
            $this->printSummary($summary);

            return self::SUCCESS;
        }

        [$apiUrl, $apiUser, $apiKey] = $credentials;

        foreach ($batches as $batch) {
            try {
                $response = $this->apiRequest($apiUser, $apiKey)
                    ->withHeader('Idempotency-Key', $this->idempotencyKey($batch))
                    ->post($apiUrl, ['items' => $batch]);
            } catch (Throwable) {
                $this->components->error('The import API request failed.');
                $this->printSummary($summary);

                return self::FAILURE;
            }

            if (! $response->successful()) {
                $this->components->error('The import API request failed.');
                $this->printSummary($summary);

                return self::FAILURE;
            }

            $ledger = $this->validatedLedger($response, count($batch));

            if ($ledger === null) {
                $this->components->error('The import API returned an invalid import ledger.');
                $this->printSummary($summary);

                return self::FAILURE;
            }

            foreach (['created', 'existing', 'failed'] as $status) {
                $summary[$status] += $ledger[$status];
            }
        }

        $this->printSummary($summary);

        if ($summary['failed'] > 0) {
            $this->components->error('One or more media items failed to import.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array{string, string, string}|null */
    private function credentials(): ?array
    {
        $apiUrl = trim((string) config('media-import.api_url'));
        $apiUser = trim((string) config('media-import.api_user'));
        $apiKey = trim((string) config('media-import.api_key'));

        if ($apiUrl === '' || $apiUser === '' || $apiKey === '') {
            return null;
        }

        return [$apiUrl, $apiUser, $apiKey];
    }

    private function normalizeOrigin(string $site): ?string
    {
        $site = trim($site);

        if ($site === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $site)) {
            $site = 'https://'.$site;
        }

        $parts = parse_url($site);

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'], $parts['pass'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    /** @return array{list<array{source_url: string, title: ?string, alt: ?string}>, int} */
    private function enumerateWordPressMedia(string $origin): array
    {
        $request = Http::acceptJson()
            ->connectTimeout((int) config('media-import.connect_timeout', 5))
            ->timeout((int) config('media-import.timeout', 30));

        $firstPage = $this->wordpressPage($request, $origin, 1);
        $totalPages = max(1, (int) $firstPage->header('X-WP-TotalPages', 1));
        $records = $this->wordpressRecords($firstPage);

        for ($page = 2; $page <= $totalPages; $page++) {
            $records = [...$records, ...$this->wordpressRecords(
                $this->wordpressPage($request, $origin, $page),
            )];
        }

        $items = [];
        $skipped = 0;

        foreach ($records as $record) {
            $item = $this->normalizeWordPressRecord($record);

            if ($item === null) {
                $skipped++;

                continue;
            }

            $items[] = $item;
        }

        return [$items, $skipped];
    }

    private function wordpressPage(PendingRequest $request, string $origin, int $page): Response
    {
        $response = $request->get($origin.'/wp-json/wp/v2/media', [
            'per_page' => 100,
            'page' => $page,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('WordPress media request failed.');
        }

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function wordpressRecords(Response $response): array
    {
        $records = $response->json();

        if (! is_array($records) || ! array_is_list($records)) {
            throw new \RuntimeException('WordPress media response was invalid.');
        }

        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new \RuntimeException('WordPress media response was invalid.');
            }
        }

        return $records;
    }

    /** @param array<string, mixed> $record
     * @return array{source_url: string, title: ?string, alt: ?string}|null
     */
    private function normalizeWordPressRecord(array $record): ?array
    {
        if (! isset($record['mime_type'])
            || ! is_string($record['mime_type'])
            || ! in_array(strtolower(trim($record['mime_type'])), self::SUPPORTED_MIME_TYPES, true)
            || ! isset($record['source_url'])
            || ! is_string($record['source_url'])
            || ! filter_var($record['source_url'], FILTER_VALIDATE_URL)
            || ! in_array(strtolower((string) parse_url($record['source_url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        $renderedTitle = $record['title']['rendered'] ?? null;
        $title = is_string($renderedTitle)
            ? $this->nullableString(html_entity_decode($renderedTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
        $alt = isset($record['alt_text']) && is_string($record['alt_text'])
            ? $this->nullableString($record['alt_text'])
            : null;

        return [
            'source_url' => trim($record['source_url']),
            'title' => $title,
            'alt' => $alt,
        ];
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function apiRequest(string $user, string $key): PendingRequest
    {
        return Http::withBasicAuth($user, $key)
            ->acceptJson()
            ->connectTimeout((int) config('media-import.connect_timeout', 5))
            ->timeout((int) config('media-import.timeout', 30));
    }

    /** @param list<array{source_url: string, title: ?string, alt: ?string}> $batch */
    private function idempotencyKey(array $batch): string
    {
        return 'media-import-'.hash('sha256', json_encode(
            $batch,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    /** @return array{requested: int, created: int, existing: int, failed: int}|null */
    private function validatedLedger(Response $response, int $batchSize): ?array
    {
        $payload = $response->json();
        $summary = is_array($payload) ? ($payload['summary'] ?? null) : null;
        $items = is_array($payload) ? ($payload['items'] ?? null) : null;
        $statuses = ['created' => 0, 'existing' => 0, 'failed' => 0];

        if (! is_array($summary) || ! is_array($items) || ! array_is_list($items) || count($items) !== $batchSize) {
            return null;
        }

        foreach (['requested', 'created', 'existing', 'failed'] as $field) {
            if (! array_key_exists($field, $summary) || ! is_int($summary[$field]) || $summary[$field] < 0) {
                return null;
            }
        }

        if ($summary['requested'] !== $batchSize
            || $batchSize !== $summary['created'] + $summary['existing'] + $summary['failed']) {
            return null;
        }

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['status']) || ! array_key_exists($item['status'], $statuses)) {
                return null;
            }

            $statuses[$item['status']]++;
        }

        if ($statuses['created'] !== $summary['created']
            || $statuses['existing'] !== $summary['existing']
            || $statuses['failed'] !== $summary['failed']) {
            return null;
        }

        return [
            'requested' => $summary['requested'],
            'created' => $summary['created'],
            'existing' => $summary['existing'],
            'failed' => $summary['failed'],
        ];
    }

    /** @param array{requested: int, created: int, existing: int, failed: int, skipped: int, batches: int} $summary */
    private function printSummary(array $summary): void
    {
        $this->line("Requested: {$summary['requested']}");
        $this->line("Created: {$summary['created']}");
        $this->line("Existing: {$summary['existing']}");
        $this->line("Failed: {$summary['failed']}");
        $this->line("Skipped: {$summary['skipped']}");
        $this->line("Batches: {$summary['batches']}");
    }
}
