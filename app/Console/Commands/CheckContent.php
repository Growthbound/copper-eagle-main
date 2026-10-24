<?php

namespace App\Console\Commands;

use App\Content\AreaRepository;
use App\Content\BenefitRepository;
use App\Content\ContentValidator;
use App\Content\FaqRepository;
use App\Content\InvalidPage;
use App\Content\InvalidRecord;
use App\Content\PageRepository;
use App\Content\ProcessStepRepository;
use App\Content\ResourceRepository;
use App\Content\ServiceRepository;
use App\Content\TestimonialRepository;
use App\Models\ContentEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Validates every page file.
 *
 * This is the guardrail for whoever is authoring content — run it before
 * committing and a broken page is caught as a readable message rather than as
 * a white screen after deploy. Messages name the file and say what to change.
 */
class CheckContent extends Command
{
    protected $signature = 'content:check';

    protected $description = 'Validate every page in content/pages and the blocks it references';

    /** Search engines truncate beyond roughly these lengths. */
    private const TITLE_LIMIT = 60;

    private const DESCRIPTION_LIMIT = 160;

    public function handle(
        PageRepository $pages,
        ContentValidator $validator,
    ): int {
        $slugs = $pages->slugs();

        if ($slugs->isEmpty()) {
            $this->components->warn('No pages found in content/pages.');
        }

        $errors = [];
        $warnings = [];

        foreach ($slugs as $slug) {
            try {
                $page = $pages->find($slug);
            } catch (InvalidPage $e) {
                $errors[] = $e->describe();

                continue;
            }

            if ($page === null) {
                continue;
            }

            $label = $slug === '/' ? 'home' : $slug;

            foreach ($page->blocks as $index => $block) {
                if (! $block->exists()) {
                    $errors[] = sprintf(
                        '%s: block %d uses type "%s", but %s does not exist.',
                        $label,
                        $index + 1,
                        $block->type,
                        $block->templatePath(),
                    );
                }
            }

            if ($page->blocks === []) {
                $warnings[] = $label.': the page has no blocks, so it will render empty.';
            }

            if (mb_strlen($page->title) > self::TITLE_LIMIT) {
                $warnings[] = sprintf(
                    '%s: the title is %d characters; search results cut off around %d.',
                    $label,
                    mb_strlen($page->title),
                    self::TITLE_LIMIT,
                );
            }

            if ($page->description === null || $page->description === '') {
                $warnings[] = $label.': no description, so search engines will invent one.';
            } elseif (mb_strlen($page->description) > self::DESCRIPTION_LIMIT) {
                $warnings[] = sprintf(
                    '%s: the description is %d characters; search results cut off around %d.',
                    $label,
                    mb_strlen($page->description),
                    self::DESCRIPTION_LIMIT,
                );
            }
        }

        [$recordErrors, $recordWarnings] = $this->checkRecords();
        [$databaseErrors, $databaseWarnings, $databaseCount] = $this->checkManagedPublications($validator);
        [$fileErrors, $fileWarnings, $fileCount] = $this->checkManagedFiles($validator);
        $managedErrors = [...$databaseErrors, ...$fileErrors];
        $managedWarnings = [...$databaseWarnings, ...$fileWarnings];
        $managedCount = $databaseCount + $fileCount;

        $errors = [...$errors, ...$recordErrors, ...$managedErrors];
        $warnings = [...$warnings, ...$recordWarnings, ...$managedWarnings];

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($errors as $error) {
            $this->components->error($error);
        }

        if ($errors !== []) {
            $this->newLine();
            $this->components->error(count($errors).' problem(s) must be fixed before these pages will render.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d file page(s), %d managed publication(s), %d service(s), %d area(s), %d question(s), %d testimonial(s) checked, %d warning(s).',
            $slugs->count(),
            $managedCount,
            app(ServiceRepository::class)->all()->count(),
            app(AreaRepository::class)->all()->count(),
            app(FaqRepository::class)->all()->count(),
            app(TestimonialRepository::class)->all()->count(),
            count($warnings),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>, 2: int}
     */
    private function checkManagedFiles(ContentValidator $validator): array
    {
        $directory = base_path('content/managed');

        if (! File::isDirectory($directory)) {
            return [[], [], 0];
        }

        $errors = [];
        $warnings = [];
        $count = 0;

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $label = 'managed file '.$file->getRelativePathname();

            try {
                $document = json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);
                $identity = is_array($document['identity'] ?? null) ? $document['identity'] : [];
                $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
                $result = $validator->validate($identity, $payload);
                $count++;

                foreach ($result->errors() as $pointer => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = "{$label} {$pointer}: {$message}";
                    }
                }

                foreach ($result->warnings() as $pointer => $messages) {
                    foreach ($messages as $message) {
                        $warnings[] = "{$label} {$pointer}: {$message}";
                    }
                }

                $this->checkManagedMedia($payload, $label, $errors);
            } catch (Throwable $exception) {
                $errors[] = "{$label}: {$exception->getMessage()}";
            }
        }

        return [$errors, $warnings, $count];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function checkManagedMedia(mixed $value, string $label, array &$errors): void
    {
        if (! is_array($value)) {
            return;
        }

        if (($value['type'] ?? null) === 'image' && is_string($value['src'] ?? null)) {
            if (preg_match('#^https?://#i', $value['src']) === 1) {
                $errors[] = "{$label}: migrated images must not hotlink {$value['src']}.";
            } elseif (str_starts_with($value['src'], '/media/migrated/')
                && ! File::isFile(public_path(ltrim($value['src'], '/')))) {
                $errors[] = "{$label}: migrated image is missing {$value['src']}.";
            }
        }

        foreach ($value as $child) {
            $this->checkManagedMedia($child, $label, $errors);
        }
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>, 2: int}
     */
    private function checkManagedPublications(
        ContentValidator $validator,
    ): array {
        if (! $this->managedDatabaseIsInspectable()
            || ! Schema::hasTable('content_entries')) {
            return [[], [], 0];
        }

        $errors = [];
        $warnings = [];
        $entries = ContentEntry::query()
            ->with('publishedRevision')
            ->whereNotNull('published_revision_id')
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $revision = $entry->publishedRevision;
            $label = sprintf(
                'managed %s/%s',
                $entry->kind->value,
                $entry->slug,
            );

            if ($revision === null) {
                $errors[] = "{$label}: the published revision is missing.";

                continue;
            }

            $result = $validator->validate(
                $revision->identity,
                $revision->payload,
            );

            foreach ($result->errors() as $pointer => $messages) {
                foreach ($messages as $message) {
                    $errors[] = "{$label} {$pointer}: {$message}";
                }
            }

            foreach ($result->warnings() as $pointer => $messages) {
                foreach ($messages as $message) {
                    $warnings[] = "{$label} {$pointer}: {$message}";
                }
            }
        }

        return [$errors, $warnings, $entries->count()];
    }

    private function managedDatabaseIsInspectable(): bool
    {
        $connection = (string) config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database === ':memory:') {
            return true;
        }

        return is_string($database) && is_file($database);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function checkRecords(): array
    {
        $errors = [];
        $warnings = [];

        try {
            $services = app(ServiceRepository::class)->all();
            $areas = app(AreaRepository::class)->all();
            $faqs = app(FaqRepository::class)->all();
            $testimonials = app(TestimonialRepository::class)->all();

            // Loaded so a malformed file surfaces here rather than as a broken
            // page; they have no cross-references of their own to check.
            app(BenefitRepository::class)->all();
            app(ProcessStepRepository::class)->all();
            app(ResourceRepository::class)->all();
        } catch (InvalidRecord $e) {
            return [[$e->getMessage()], []];
        }

        foreach ($services as $service) {
            if ($service->summary() === null) {
                $warnings[] = 'services/'.$service->slug.': no summary, so its card and page have nothing to say.';
            }

            foreach ($service->areaSlugs() ?? [] as $slug) {
                if (! $areas->contains(fn ($area): bool => $area->slug === $slug)) {
                    $errors[] = sprintf(
                        'services/%s.json: lists area "%s", which has no record in content/areas.',
                        $service->slug,
                        $slug,
                    );
                }
            }
        }

        foreach ($areas as $area) {
            // A page that is another page with the place name swapped is a
            // doorway page. Search engines penalise those, so an area with
            // nothing local to say is worth knowing about before publishing.
            if (! $area->hasLocalContent()) {
                $warnings[] = sprintf(
                    'areas/%s: nothing specific to this place, so its pages will read as duplicates of every other area.',
                    $area->slug,
                );
            }
        }

        foreach ($faqs as $faq) {
            if ($faq->answer() === '') {
                $errors[] = 'faqs/'.$faq->slug.'.json: the question has no answer.';
            }

            foreach ($faq->serviceSlugs() ?? [] as $slug) {
                if (! $services->contains(fn ($service): bool => $service->slug === $slug)) {
                    $errors[] = sprintf(
                        'faqs/%s.json: scoped to service "%s", which has no record in content/services.',
                        $faq->slug,
                        $slug,
                    );
                }
            }

            foreach ($faq->areaSlugs() ?? [] as $slug) {
                if (! $areas->contains(fn ($area): bool => $area->slug === $slug)) {
                    $errors[] = sprintf(
                        'faqs/%s.json: scoped to area "%s", which has no record in content/areas.',
                        $faq->slug,
                        $slug,
                    );
                }
            }
        }

        foreach ($testimonials as $testimonial) {
            if ($testimonial->quote() === '') {
                $errors[] = 'testimonials/'.$testimonial->slug.'.json: there is no quote.';
            }

            foreach ($testimonial->serviceSlugs() ?? [] as $slug) {
                if (! $services->contains(fn ($service): bool => $service->slug === $slug)) {
                    $errors[] = sprintf(
                        'testimonials/%s.json: scoped to service "%s", which has no record in content/services.',
                        $testimonial->slug,
                        $slug,
                    );
                }
            }

            foreach ($testimonial->areaSlugs() ?? [] as $slug) {
                if (! $areas->contains(fn ($area): bool => $area->slug === $slug)) {
                    $errors[] = sprintf(
                        'testimonials/%s.json: scoped to area "%s", which has no record in content/areas.',
                        $testimonial->slug,
                        $slug,
                    );
                }
            }
        }

        return [$errors, $warnings];
    }
}
