<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Content\Validation\ContentValidationResult;
use App\Models\ApiKey;
use App\Models\ContentAuditEvent;
use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final readonly class ContentLifecycle
{
    public function __construct(
        private ContentValidator $validator,
        private MediaUsageIndexer $mediaUsageIndexer,
        private ContentPreviewService $previews,
        private SiteStructureRepository $siteStructure,
        private ContentIdentityClaims $identityClaims,
    ) {}

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    public function createDraft(
        array $identity,
        array $payload,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentEntry {
        $snapshot = $this->snapshotIdentity($identity);
        $validation = $this->validator->validateDraft($snapshot, $payload)
            ->merge($this->identityAvailabilityResult(null, $snapshot));

        if ($validation->hasErrors()) {
            throw new ContentValidationFailed($validation);
        }

        try {
            return DB::transaction(function () use ($snapshot, $payload, $actor, $apiKey): ContentEntry {
                $this->guardIdentityAvailable(null, $snapshot);
                $entry = ContentEntry::create([
                    ...$snapshot,
                    'status' => ContentStatus::Draft,
                    'lock_version' => 0,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $this->identityClaims->claimCurrent($entry, $snapshot);
                $revision = $entry->revisions()->create([
                    'revision_number' => 1,
                    'schema_version' => 1,
                    'identity' => $snapshot,
                    'payload' => $payload,
                    'created_by' => $actor->id,
                    'api_key_id' => $apiKey?->id,
                    'source' => $apiKey === null ? ContentSource::Admin : ContentSource::Api,
                ]);

                $this->mediaUsageIndexer->index($revision);
                $this->audit($entry, $revision, $actor, $apiKey, 'create');

                return $entry->fresh();
            });
        } catch (QueryException $exception) {
            if ($this->isIdentityUniqueConstraintViolation($exception)) {
                throw new ContentValidationFailed(
                    $this->identityAvailabilityResult(null, $snapshot),
                );
            }

            throw $exception;
        }
    }

    /**
     * Adopt a legacy JSON snapshot as an immutable draft revision.
     *
     * Import is the only lane allowed to retain an invalid draft so legacy
     * content can be repaired in the editor. Preview and publication still
     * run the normal validator and remain fail closed.
     *
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    public function importDraft(
        array $identity,
        array $payload,
        User $actor,
        string $sourceIdentity,
    ): ContentRevision {
        $snapshot = $this->snapshotIdentity($identity);
        $validation = $this->validator->validate($snapshot, $payload);

        try {
            return DB::transaction(function () use (
                $snapshot,
                $payload,
                $actor,
                $sourceIdentity,
                $validation,
            ): ContentRevision {
                $kind = $this->contentKind($snapshot['kind']);
                $slug = $snapshot['slug'];
                $template = $this->pageTemplate($snapshot['template']);
                $entry = ContentEntry::query()
                    ->where('kind', $kind->value)
                    ->where('slug', $slug)
                    ->lockForUpdate()
                    ->first();
                $this->guardIdentityAvailable($entry, $snapshot);

                if ($entry !== null && $entry->template !== $template) {
                    throw new ContentValidationFailed(new ContentValidationResult([
                        '/identity/template' => ['The imported template does not match the existing content entry.'],
                    ]));
                }

                if ($entry !== null) {
                    $existing = $entry->revisions()
                        ->where('source', ContentSource::Import->value)
                        ->where('summary', $sourceIdentity)
                        ->first();

                    if ($existing !== null) {
                        return $existing;
                    }
                } else {
                    $entry = ContentEntry::create([
                        'kind' => $kind,
                        'slug' => $slug,
                        'title' => $snapshot['title'],
                        'template' => $template,
                        'status' => ContentStatus::Draft,
                        'lock_version' => 0,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                    $this->identityClaims->claimCurrent($entry, $snapshot);
                }

                $revision = $entry->revisions()->create([
                    'revision_number' => ((int) $entry->revisions()->max('revision_number')) + 1,
                    'schema_version' => 1,
                    'identity' => $snapshot,
                    'payload' => $payload,
                    'summary' => $sourceIdentity,
                    'created_by' => $actor->id,
                    'source' => ContentSource::Import,
                ]);

                $this->mediaUsageIndexer->index($revision, allowUnresolved: true);

                if ($revision->revision_number > 1) {
                    if ($entry->published_revision_id === null) {
                        $this->identityClaims->moveCurrent($entry, $snapshot);
                    }

                    $entry->forceFill([
                        ...($entry->published_revision_id === null ? $snapshot : []),
                        'lock_version' => $entry->lock_version + 1,
                        'updated_by' => $actor->id,
                    ])->save();
                }

                $this->audit(
                    $entry,
                    $revision,
                    $actor,
                    null,
                    'import',
                    [
                        'source_identity' => $sourceIdentity,
                        'validation' => $validation->toArray(),
                    ],
                );

                return $revision;
            });
        } catch (QueryException $exception) {
            if ($this->isIdentityUniqueConstraintViolation($exception)) {
                throw new ContentValidationFailed(
                    $this->identityAvailabilityResult(null, $snapshot),
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $identity
     */
    public function saveRevision(
        ContentEntry $entry,
        array $payload,
        int $expectedLockVersion,
        User $actor,
        ContentSource $source,
        ?ApiKey $apiKey = null,
        ?string $summary = null,
        ?array $identity = null,
    ): ContentRevision {
        try {
            [$revision, $validation] = DB::transaction(function () use (
                $entry,
                $payload,
                $expectedLockVersion,
                $actor,
                $source,
                $apiKey,
                $summary,
                $identity,
            ): array {
                $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
                $this->guardVersion($stored, $expectedLockVersion);
                $candidateIdentity = $identity ?? $this->identity($stored);
                $snapshot = $this->snapshotIdentity($candidateIdentity);
                $this->guardIdentityAvailable($stored, $snapshot);
                $validation = $this->validator->validateDraft(
                    $snapshot,
                    $payload,
                    $this->retiringPublicPaths($stored, $snapshot),
                );

                if ($validation->hasErrors()) {
                    $this->audit(
                        $stored,
                        null,
                        $actor,
                        $apiKey,
                        'validation_failed',
                        ['errors' => $validation->errors()],
                    );

                    return [null, $validation];
                }

                $revision = $stored->revisions()->create([
                    'revision_number' => ((int) $stored->revisions()->max('revision_number')) + 1,
                    'schema_version' => 1,
                    'identity' => $snapshot,
                    'payload' => $payload,
                    'summary' => $this->nullableTrim($summary),
                    'created_by' => $actor->id,
                    'api_key_id' => $apiKey?->id,
                    'source' => $source,
                ]);

                $this->mediaUsageIndexer->index($revision);

                if ($stored->published_revision_id === null) {
                    $this->identityClaims->moveCurrent($stored, $snapshot);
                }

                $stored->forceFill([
                    ...($stored->published_revision_id === null ? $snapshot : []),
                    'lock_version' => $stored->lock_version + 1,
                    'updated_by' => $actor->id,
                ])->save();
                $this->audit(
                    $stored,
                    $revision,
                    $actor,
                    $apiKey,
                    'save_revision',
                    ['summary' => $revision->summary, 'source' => $source->value],
                );

                return [$revision, null];
            });
        } catch (QueryException $exception) {
            if ($this->isIdentityUniqueConstraintViolation($exception)) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/identity/slug' => ['This public path is already owned by another content entry.'],
                ]));
            }

            throw $exception;
        }

        if ($validation instanceof ContentValidationResult) {
            throw new ContentValidationFailed($validation);
        }

        if (! $revision instanceof ContentRevision) {
            throw new LogicException('Content revision transaction returned no revision.');
        }

        return $revision;
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    public function validateCandidate(
        ContentEntry $entry,
        array $identity,
        array $payload,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentValidationResult {
        $snapshot = $this->snapshotIdentity($identity);

        return DB::transaction(function () use (
            $entry,
            $snapshot,
            $payload,
            $expectedLockVersion,
            $actor,
            $apiKey,
        ): ContentValidationResult {
            $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->guardVersion($stored, $expectedLockVersion);
            $result = $this->validator->validate(
                $snapshot,
                $payload,
                $this->retiringPublicPaths($stored, $snapshot),
            )
                ->merge($this->identityAvailabilityResult($stored, $snapshot));

            $this->audit($stored, null, $actor, $apiKey, 'validate', [
                'valid' => ! $result->hasErrors(),
                'errors' => $result->errors(),
                'warnings' => $result->warnings(),
                'identity' => $snapshot,
            ]);

            return $result;
        });
    }

    public function issuePreview(
        ContentEntry $entry,
        ContentRevision $revision,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): IssuedPreview {
        [$issued, $validation] = DB::transaction(function () use (
            $entry,
            $revision,
            $expectedLockVersion,
            $actor,
            $apiKey,
        ): array {
            $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->guardVersion($stored, $expectedLockVersion);
            $this->guardRevision($stored, $revision);
            $this->guardIdentityAvailable(
                $stored,
                $this->identityForRevision($stored, $revision),
            );
            $this->mediaUsageIndexer->index($revision);
            $validation = $this->validator->validate(
                $this->identityForRevision($stored, $revision),
                $revision->payload,
                $this->retiringPublicPaths(
                    $stored,
                    $this->identityForRevision($stored, $revision),
                ),
            );

            if ($validation->hasErrors()) {
                $this->audit(
                    $stored,
                    $revision,
                    $actor,
                    $apiKey,
                    'validation_failed',
                    ['errors' => $validation->errors()],
                );

                return [null, $validation];
            }

            $issued = $this->previews->issue($stored, $revision, $actor);
            $this->audit($stored, $revision, $actor, $apiKey, 'preview');

            return [$issued, null];
        });

        if ($validation instanceof ContentValidationResult) {
            throw new ContentValidationFailed($validation);
        }

        if (! $issued instanceof IssuedPreview) {
            throw new LogicException('Content preview transaction returned no preview.');
        }

        return $issued;
    }

    public function publish(
        ContentEntry $entry,
        ContentRevision $revision,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentEntry {
        try {
            [$published, $validation] = DB::transaction(function () use (
                $entry,
                $revision,
                $expectedLockVersion,
                $actor,
                $apiKey,
            ): array {
                $this->siteStructure->lockPublicationGraph();
                $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
                $this->guardVersion($stored, $expectedLockVersion);
                $this->guardRevision($stored, $revision);
                $identity = $this->identityForRevision($stored, $revision);
                $this->guardIdentityAvailable($stored, $identity);
                $this->guardPublicPathTransition($stored, $identity);
                $this->mediaUsageIndexer->index($revision);
                $validation = $this->validator->validate(
                    $identity,
                    $revision->payload,
                    $this->retiringPublicPaths($stored, $identity),
                );

                if ($validation->hasErrors()) {
                    $this->audit(
                        $stored,
                        $revision,
                        $actor,
                        $apiKey,
                        'validation_failed',
                        ['errors' => $validation->errors(), 'operation' => 'publish'],
                    );

                    return [null, $validation];
                }

                $stored->forceFill([
                    ...$identity,
                    'status' => ContentStatus::Published,
                    'published_revision_id' => $revision->id,
                    'scheduled_revision_id' => null,
                    'scheduled_kind' => null,
                    'scheduled_slug' => null,
                    'scheduled_for' => null,
                    'scheduled_by' => null,
                    'scheduled_by_api_key_id' => null,
                    'published_at' => now(),
                    'lock_version' => $stored->lock_version + 1,
                    'updated_by' => $actor->id,
                ])->save();
                $this->identityClaims->promote($stored, $identity);
                $this->audit($stored, $revision, $actor, $apiKey, 'publish');

                return [$stored->fresh(), null];
            });
        } catch (QueryException $exception) {
            if ($this->isIdentityUniqueConstraintViolation($exception)) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/identity/slug' => ['This public path is already owned by another content entry.'],
                ]));
            }

            throw $exception;
        }

        if ($validation instanceof ContentValidationResult) {
            throw new ContentValidationFailed($validation);
        }

        if (! $published instanceof ContentEntry) {
            throw new LogicException('Content publication transaction returned no entry.');
        }

        return $published;
    }

    public function schedule(
        ContentEntry $entry,
        ContentRevision $revision,
        CarbonImmutable $publishAt,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentEntry {
        if (! $publishAt->isFuture()) {
            throw new ContentValidationFailed(new ContentValidationResult([
                '/scheduled_for' => ['Scheduled publication must be in the future.'],
            ]));
        }

        try {
            [$scheduled, $validation] = DB::transaction(function () use (
                $entry,
                $revision,
                $publishAt,
                $expectedLockVersion,
                $actor,
                $apiKey,
            ): array {
                $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
                $this->guardVersion($stored, $expectedLockVersion);
                $this->guardRevision($stored, $revision);
                $scheduledIdentity = $this->identityForRevision($stored, $revision);
                $this->guardIdentityAvailable($stored, $scheduledIdentity);
                $this->guardPublicPathTransition($stored, $scheduledIdentity);
                $this->mediaUsageIndexer->index($revision);
                $validation = $this->validator->validate(
                    $scheduledIdentity,
                    $revision->payload,
                    $this->retiringPublicPaths($stored, $scheduledIdentity),
                );

                if ($validation->hasErrors()) {
                    $this->audit(
                        $stored,
                        $revision,
                        $actor,
                        $apiKey,
                        'validation_failed',
                        ['errors' => $validation->errors(), 'operation' => 'schedule'],
                    );

                    return [null, $validation];
                }

                $this->identityClaims->reserveScheduled($stored, $scheduledIdentity);
                $stored->forceFill([
                    'status' => ContentStatus::Scheduled,
                    'scheduled_revision_id' => $revision->id,
                    'scheduled_kind' => $scheduledIdentity['kind'],
                    'scheduled_slug' => $scheduledIdentity['slug'],
                    'scheduled_for' => $publishAt,
                    'scheduled_by' => $actor->id,
                    'scheduled_by_api_key_id' => $apiKey?->id,
                    'lock_version' => $stored->lock_version + 1,
                    'updated_by' => $actor->id,
                ])->save();
                $this->audit(
                    $stored,
                    $revision,
                    $actor,
                    $apiKey,
                    'schedule',
                    ['scheduled_for' => $publishAt->toIso8601String()],
                );

                return [$stored->fresh(), null];
            });
        } catch (QueryException $exception) {
            if ($this->isScheduledIdentityUniqueConstraintViolation($exception)) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/identity/slug' => ['This public path is already reserved by another scheduled entry.'],
                ]));
            }

            throw $exception;
        }

        if ($validation instanceof ContentValidationResult) {
            throw new ContentValidationFailed($validation);
        }

        if (! $scheduled instanceof ContentEntry) {
            throw new LogicException('Content scheduling transaction returned no entry.');
        }

        return $scheduled;
    }

    public function cancelSchedule(
        ContentEntry $entry,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentEntry {
        return DB::transaction(function () use (
            $entry,
            $expectedLockVersion,
            $actor,
            $apiKey,
        ): ContentEntry {
            $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->guardVersion($stored, $expectedLockVersion);

            if ($stored->scheduled_revision_id === null) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/scheduled_revision_id' => ['This content entry has no scheduled revision.'],
                ]));
            }

            $this->identityClaims->releaseScheduled($stored);
            $stored->forceFill([
                'status' => $stored->published_revision_id === null
                    ? ContentStatus::Draft
                    : ContentStatus::Published,
                'scheduled_revision_id' => null,
                'scheduled_kind' => null,
                'scheduled_slug' => null,
                'scheduled_for' => null,
                'scheduled_by' => null,
                'scheduled_by_api_key_id' => null,
                'lock_version' => $stored->lock_version + 1,
                'updated_by' => $actor->id,
            ])->save();
            $this->audit($stored, null, $actor, $apiKey, 'cancel_schedule');

            return $stored->fresh();
        });
    }

    public function rollback(
        ContentEntry $entry,
        ContentRevision $revision,
        int $expectedLockVersion,
        User $actor,
        ContentSource $source,
        ?ApiKey $apiKey = null,
    ): ContentRevision {
        try {
            [$rollback, $validation] = DB::transaction(function () use (
                $entry,
                $revision,
                $expectedLockVersion,
                $actor,
                $source,
                $apiKey,
            ): array {
                $this->siteStructure->lockPublicationGraph();
                $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
                $this->guardVersion($stored, $expectedLockVersion);
                $this->guardRevision($stored, $revision);
                $identity = $this->identityForRevision($stored, $revision);
                $this->guardIdentityAvailable($stored, $identity);
                $this->guardPublicPathTransition($stored, $identity);
                $this->mediaUsageIndexer->index($revision);
                $validation = $this->validator->validate(
                    $identity,
                    $revision->payload,
                    $this->retiringPublicPaths($stored, $identity),
                );

                if ($validation->hasErrors()) {
                    $this->audit(
                        $stored,
                        $revision,
                        $actor,
                        $apiKey,
                        'validation_failed',
                        ['errors' => $validation->errors(), 'operation' => 'rollback'],
                    );

                    return [null, $validation];
                }

                $rollback = $stored->revisions()->create([
                    'revision_number' => ((int) $stored->revisions()->max('revision_number')) + 1,
                    'schema_version' => $revision->schema_version,
                    'identity' => $identity,
                    'payload' => $revision->payload,
                    'summary' => "Rollback to revision {$revision->revision_number}",
                    'created_by' => $actor->id,
                    'api_key_id' => $apiKey?->id,
                    'source' => $source,
                ]);
                $this->mediaUsageIndexer->index($rollback);
                $stored->forceFill([
                    ...$identity,
                    'status' => ContentStatus::Published,
                    'published_revision_id' => $rollback->id,
                    'scheduled_revision_id' => null,
                    'scheduled_kind' => null,
                    'scheduled_slug' => null,
                    'scheduled_for' => null,
                    'scheduled_by' => null,
                    'scheduled_by_api_key_id' => null,
                    'published_at' => now(),
                    'lock_version' => $stored->lock_version + 1,
                    'updated_by' => $actor->id,
                ])->save();
                $this->identityClaims->promote($stored, $identity);
                $this->audit(
                    $stored,
                    $rollback,
                    $actor,
                    $apiKey,
                    'rollback',
                    ['from_revision' => $revision->revision_number],
                );

                return [$rollback, null];
            });
        } catch (QueryException $exception) {
            if ($this->isIdentityUniqueConstraintViolation($exception)) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/identity/slug' => ['This public path is already owned by another content entry.'],
                ]));
            }

            throw $exception;
        }

        if ($validation instanceof ContentValidationResult) {
            throw new ContentValidationFailed($validation);
        }

        if (! $rollback instanceof ContentRevision) {
            throw new LogicException('Content rollback transaction returned no revision.');
        }

        return $rollback;
    }

    public function archive(
        ContentEntry $entry,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentEntry {
        return DB::transaction(function () use (
            $entry,
            $expectedLockVersion,
            $actor,
            $apiKey,
        ): ContentEntry {
            $this->siteStructure->lockPublicationGraph();
            $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->guardVersion($stored, $expectedLockVersion);

            if ($stored->kind === ContentKind::Page
                && $stored->published_revision_id !== null
                && $this->siteStructure->referencesPath(
                    $this->publicPath($stored->slug),
                    lockForUpdate: true,
                )) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/status' => ['Remove this page from all published links before archiving it.'],
                ]));
            }

            return $this->applyStatus(
                $stored,
                $actor,
                $apiKey,
                ContentStatus::Archived,
                'archive',
            );
        });
    }

    public function restore(
        ContentEntry $entry,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey = null,
    ): ContentEntry {
        return DB::transaction(function () use (
            $entry,
            $expectedLockVersion,
            $actor,
            $apiKey,
        ): ContentEntry {
            $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->guardVersion($stored, $expectedLockVersion);

            if ($stored->status !== ContentStatus::Archived) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    '/status' => ['Only archived content can be restored.'],
                ]));
            }

            return $this->applyStatus(
                $stored,
                $actor,
                $apiKey,
                ContentStatus::Draft,
                'restore',
            );
        });
    }

    private function changeStatus(
        ContentEntry $entry,
        int $expectedLockVersion,
        User $actor,
        ?ApiKey $apiKey,
        ContentStatus $status,
        string $action,
    ): ContentEntry {
        return DB::transaction(function () use (
            $entry,
            $expectedLockVersion,
            $actor,
            $apiKey,
            $status,
            $action,
        ): ContentEntry {
            $stored = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->guardVersion($stored, $expectedLockVersion);

            return $this->applyStatus($stored, $actor, $apiKey, $status, $action);
        });
    }

    private function applyStatus(
        ContentEntry $entry,
        User $actor,
        ?ApiKey $apiKey,
        ContentStatus $status,
        string $action,
    ): ContentEntry {
        $this->identityClaims->releaseScheduled($entry);
        $entry->forceFill([
            'status' => $status,
            'published_revision_id' => null,
            'scheduled_revision_id' => null,
            'scheduled_kind' => null,
            'scheduled_slug' => null,
            'scheduled_for' => null,
            'scheduled_by' => null,
            'scheduled_by_api_key_id' => null,
            'published_at' => null,
            'lock_version' => $entry->lock_version + 1,
            'updated_by' => $actor->id,
        ])->save();
        $this->audit($entry, null, $actor, $apiKey, $action);

        return $entry->fresh();
    }

    private function guardVersion(ContentEntry $entry, int $expected): void
    {
        if ($entry->lock_version !== $expected) {
            throw new ContentConflict($expected, $entry->lock_version);
        }
    }

    private function guardRevision(
        ContentEntry $entry,
        ContentRevision $revision,
    ): void {
        if ($revision->content_entry_id !== $entry->id) {
            throw new ContentValidationFailed(new ContentValidationResult([
                '/revision' => ['The revision does not belong to this content entry.'],
            ]));
        }
    }

    /** @param array<string, mixed> $identity */
    private function guardIdentityAvailable(
        ?ContentEntry $entry,
        array $identity,
    ): void {
        $result = $this->identityAvailabilityResult($entry, $identity);

        if ($result->hasErrors()) {
            throw new ContentValidationFailed($result);
        }
    }

    /** @param array<string, mixed> $identity */
    private function identityAvailabilityResult(
        ?ContentEntry $entry,
        array $identity,
    ): ContentValidationResult {
        return $this->identityClaims->availability($entry, [
            'kind' => $this->contentKind($identity['kind'])->value,
            'slug' => $this->normalizeSlug($identity['slug']),
        ]);
    }

    /** @param array<string, mixed> $identity */
    private function guardPublicPathTransition(
        ContentEntry $entry,
        array $identity,
    ): void {
        if ($entry->kind !== ContentKind::Page
            || $entry->published_revision_id === null) {
            return;
        }

        $nextKind = $this->contentKind($identity['kind']);
        $nextSlug = $this->normalizeSlug($identity['slug']);

        if (($nextKind === ContentKind::Page && $entry->slug === $nextSlug)
            || ! $this->siteStructure->referencesPath($this->publicPath($entry->slug))) {
            return;
        }

        $pointer = $nextKind === ContentKind::Page
            ? '/identity/slug'
            : '/identity/kind';

        throw new ContentValidationFailed(new ContentValidationResult([
            $pointer => ['Update all published links before changing this public path.'],
        ]));
    }

    private function publicPath(string $slug): string
    {
        $slug = $this->normalizeSlug($slug);

        return $slug === '/' ? '/' : '/'.$slug;
    }

    /**
     * Paths served only by the entry's current published page identity and
     * therefore unavailable after the candidate identity takes effect.
     *
     * @param  array<string, mixed>  $identity
     * @return array<int, string>
     */
    private function retiringPublicPaths(
        ContentEntry $entry,
        array $identity,
    ): array {
        if ($entry->kind !== ContentKind::Page
            || $entry->published_revision_id === null) {
            return [];
        }

        $nextKind = $this->contentKind($identity['kind']);
        $nextSlug = $this->normalizeSlug($identity['slug']);

        if ($nextKind === ContentKind::Page && $entry->slug === $nextSlug) {
            return [];
        }

        return [$this->publicPath($entry->slug)];
    }

    private function isIdentityUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && ((str_contains($message, 'content_entries')
                    && (str_contains($message, 'content_entries_kind_slug_unique')
                        || (str_contains($message, 'kind') && str_contains($message, 'slug'))))
                || str_contains($message, 'content_identity_claims'));
    }

    private function isScheduledIdentityUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && ((str_contains($message, 'content_entries')
                    && (str_contains($message, 'content_entries_scheduled_identity_unique')
                        || (str_contains($message, 'scheduled_kind')
                            && str_contains($message, 'scheduled_slug'))))
                || str_contains($message, 'content_identity_claims'));
    }

    /**
     * @return array<string, ContentKind|PageTemplate|string>
     */
    private function identity(ContentEntry $entry): array
    {
        return [
            'kind' => $entry->kind,
            'slug' => $entry->slug,
            'title' => $entry->title,
            'template' => $entry->template,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array{kind: string, slug: string, title: string, template: string}
     */
    private function snapshotIdentity(array $identity): array
    {
        return [
            'kind' => $this->contentKind($identity['kind'])->value,
            'slug' => $this->normalizeSlug($identity['slug']),
            'title' => trim((string) $identity['title']),
            'template' => $this->pageTemplate($identity['template'])->value,
        ];
    }

    /**
     * @return array{kind: string, slug: string, title: string, template: string}
     */
    private function identityForRevision(
        ContentEntry $entry,
        ContentRevision $revision,
    ): array {
        return is_array($revision->identity)
            ? $this->snapshotIdentity($revision->identity)
            : $this->snapshotIdentity($this->identity($entry));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(
        ContentEntry $entry,
        ?ContentRevision $revision,
        User $actor,
        ?ApiKey $apiKey,
        string $action,
        array $metadata = [],
    ): void {
        ContentAuditEvent::create([
            'content_entry_id' => $entry->id,
            'content_revision_id' => $revision?->id,
            'user_id' => $actor->id,
            'api_key_id' => $apiKey?->id,
            'action' => $action,
            'request_id' => $this->requestId(),
            'metadata' => $metadata,
        ]);
    }

    private function requestId(): string
    {
        $header = app()->bound('request')
            ? request()->header('X-Request-ID')
            : null;

        return is_string($header) && trim($header) !== ''
            ? trim($header)
            : (string) Str::uuid();
    }

    private function normalizeSlug(mixed $slug): string
    {
        $normalized = trim((string) $slug, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function contentKind(mixed $kind): ContentKind
    {
        return $kind instanceof ContentKind
            ? $kind
            : ContentKind::from((string) $kind);
    }

    private function pageTemplate(mixed $template): PageTemplate
    {
        return $template instanceof PageTemplate
            ? $template
            : PageTemplate::from((string) $template);
    }
}
