<?php

namespace App\Console\Commands;

use App\Content\ContentLifecycle;
use App\Content\ContentValidator;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\PageTemplate;
use App\Content\InvalidPage;
use App\Content\Page;
use App\Content\PageRepository;
use App\Models\ContentRevision;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class ImportJsonContent extends Command
{
    protected $signature = 'content:import-json
        {--dry-run : Validate and report without writing}
        {--kind=* : Import only these content kinds}
        {--slug=* : Import only these exact JSON page slugs}';

    protected $description = 'Adopt legacy JSON pages as deterministic managed draft revisions';

    public function handle(
        PageRepository $pages,
        ContentValidator $validator,
        ContentLifecycle $lifecycle,
    ): int {
        $kinds = collect($this->option('kind'))
            ->filter(fn (mixed $kind): bool => is_string($kind) && $kind !== '')
            ->values();

        if ($kinds->contains(fn (string $kind): bool => ContentKind::tryFrom($kind) === null)) {
            $this->components->error('Every --kind value must be a supported content kind.');

            return self::FAILURE;
        }

        if ($kinds->isNotEmpty() && ! $kinds->contains(ContentKind::Page->value)) {
            $this->components->info('No JSON-backed page records matched the requested content kinds.');

            return self::SUCCESS;
        }

        $requestedSlugs = collect($this->option('slug'))
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->map(fn (string $slug): string => $this->normalizeSlug($slug))
            ->unique()
            ->values();
        $slugs = $pages->slugs()
            ->when(
                $requestedSlugs->isNotEmpty(),
                fn ($slugs) => $slugs->filter(fn (string $slug): bool => $requestedSlugs->contains($slug)),
            )
            ->sort()
            ->values();

        if ($slugs->isEmpty()) {
            $this->components->warn('No JSON pages matched the requested filters.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $actor = $dryRun ? null : User::query()->orderBy('id')->first();

        if (! $dryRun && $actor === null) {
            $this->components->error('Create an owner account before importing managed content.');

            return self::FAILURE;
        }

        $failed = 0;
        $written = 0;
        $unchanged = 0;

        foreach ($slugs as $slug) {
            try {
                $candidate = $this->candidate($pages, $slug);
            } catch (InvalidPage|JsonException $exception) {
                $this->components->error("{$slug}: {$exception->getMessage()}");
                $failed++;

                continue;
            }

            $validation = $validator->validate($candidate['identity'], $candidate['payload']);

            if ($dryRun) {
                $label = $validation->hasErrors() ? 'adoptable with repair required' : 'valid';
                $this->line("{$slug}: {$label}");

                continue;
            }

            $alreadyImported = ContentRevision::query()
                ->where('source', ContentSource::Import->value)
                ->where('summary', $candidate['source_identity'])
                ->exists();

            if ($alreadyImported) {
                $unchanged++;

                continue;
            }

            $lifecycle->importDraft(
                $candidate['identity'],
                $candidate['payload'],
                $actor,
                $candidate['source_identity'],
            );
            $written++;
        }

        $this->components->info(
            $dryRun
                ? "Dry run complete: {$slugs->count()} candidate(s), {$failed} unreadable."
                : "Import complete: {$written} revision(s) written, {$unchanged} unchanged, {$failed} failed.",
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{
     *     identity: array<string, mixed>,
     *     payload: array<string, mixed>,
     *     source_identity: string
     * }
     *
     * @throws InvalidPage|JsonException
     */
    private function candidate(PageRepository $pages, string $slug): array
    {
        $page = $pages->find($slug);

        if (! $page instanceof Page) {
            throw new InvalidPage($slug, 'The page could not be loaded.');
        }

        $raw = File::get($page->sourcePath);
        $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new InvalidPage($slug, 'The file must contain a JSON object.');
        }

        $relativePath = str_replace(
            [base_path().DIRECTORY_SEPARATOR, '\\'],
            ['', '/'],
            $page->sourcePath,
        );
        $sourceIdentity = sprintf(
            'import:%s:sha256:%s',
            $relativePath,
            hash('sha256', $raw),
        );

        return [
            'identity' => [
                'kind' => ContentKind::Page,
                'slug' => $slug,
                'title' => $page->title,
                'template' => PageTemplate::Standard,
            ],
            'payload' => $payload,
            'source_identity' => $sourceIdentity,
        ];
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = trim($slug, '/');

        return $normalized === '' || $normalized === 'home' ? '/' : $normalized;
    }
}
