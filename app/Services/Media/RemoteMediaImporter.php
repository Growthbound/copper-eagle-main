<?php

namespace App\Services\Media;

use App\Enums\MediaKind;
use App\Models\Media;
use App\ValueObjects\MediaImportItem;
use App\ValueObjects\MediaImportResult;
use finfo;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;
use Illuminate\Container\Attributes\Storage as StorageDisk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use SplFileInfo;
use Throwable;

class RemoteMediaImporter
{
    /** @var array<string, array{MediaKind, string}> */
    private const MIME_TYPES = [
        'image/jpeg' => [MediaKind::Image, 'jpg'],
        'image/png' => [MediaKind::Image, 'png'],
        'image/webp' => [MediaKind::Image, 'webp'],
        'image/avif' => [MediaKind::Image, 'avif'],
        'image/gif' => [MediaKind::Image, 'gif'],
        'video/mp4' => [MediaKind::Video, 'mp4'],
    ];

    private readonly Filesystem $publicDisk;

    public function __construct(
        private readonly RemoteMediaFetcher $fetcher,
        #[StorageDisk('public')]
        ?Filesystem $publicDisk = null,
    ) {
        $this->publicDisk = $publicDisk ?? Storage::disk('public');
    }

    public function import(MediaImportItem $item): MediaImportResult
    {
        $sourceHash = hash('sha256', $this->normalizeSourceUrl($item->sourceUrl));
        $existing = Media::query()->where('source_hash', $sourceHash)->first();

        if ($existing !== null && $this->publicDisk->exists($existing->path)) {
            return new MediaImportResult('existing', $item->sourceUrl, $existing);
        }

        if ($existing === null && $item->handle !== null && Media::query()->where('handle', $item->handle)->exists()) {
            return $this->failure(
                $item,
                'handle_conflict',
                'The requested handle is already assigned to another media item.',
            );
        }

        $temporaryFile = null;

        try {
            $temporaryFile = $this->fetcher->fetch($item->sourceUrl);

            return $this->importFile($item, $temporaryFile, $sourceHash, $existing);
        } catch (UnsafeRemoteUrl|RemoteMediaFetchFailed $exception) {
            return $this->failure($item, $exception->errorCode, $exception->safeMessage);
        } finally {
            if ($temporaryFile instanceof SplFileInfo) {
                @unlink($temporaryFile->getPathname());
            }
        }
    }

    public function restore(Media $media): MediaImportResult
    {
        $sourceUrl = trim((string) $media->source_url);

        if ($sourceUrl === '') {
            return new MediaImportResult(
                status: 'failed',
                sourceUrl: '',
                errorCode: 'source_missing',
                message: 'The catalog record does not have a remote source that can be restored.',
            );
        }

        $item = new MediaImportItem(
            sourceUrl: $sourceUrl,
            title: $media->title,
            alt: $media->alt,
            handle: $media->handle,
        );
        $sourceHash = hash('sha256', $this->normalizeSourceUrl($sourceUrl));
        $temporaryFile = null;

        try {
            $temporaryFile = $this->fetcher->fetch($sourceUrl);

            return $this->importFile($item, $temporaryFile, $sourceHash, $media);
        } catch (UnsafeRemoteUrl|RemoteMediaFetchFailed $exception) {
            return $this->failure($item, $exception->errorCode, $exception->safeMessage);
        } finally {
            if ($temporaryFile instanceof SplFileInfo) {
                @unlink($temporaryFile->getPathname());
            }
        }
    }

    private function importFile(
        MediaImportItem $item,
        SplFileInfo $temporaryFile,
        string $sourceHash,
        ?Media $existing = null,
    ): MediaImportResult {
        $pathName = $temporaryFile->getPathname();
        $size = $temporaryFile->getSize();
        $maxBytes = (int) config('media-import.max_bytes');

        if ($size > $maxBytes) {
            return $this->failure($item, 'too_large', 'The remote file exceeds the allowed size.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($pathName);

        if (! is_string($mimeType) || ! isset(self::MIME_TYPES[$mimeType])) {
            return $this->failure($item, 'unsupported_mime', 'The remote file type is not supported.');
        }

        [$kind, $extension] = self::MIME_TYPES[$mimeType];
        $dimensions = $kind === MediaKind::Image ? @getimagesize($pathName) : false;

        if ($kind === MediaKind::Image && (! is_array($dimensions) || $dimensions[0] < 1 || $dimensions[1] < 1)) {
            return $this->failure($item, 'invalid_image', 'The remote image bytes are not valid.');
        }

        if ($existing !== null && $existing->kind !== $kind) {
            return $this->failure($item, 'source_kind_changed', 'The remote file is no longer the same media kind as the catalog record.');
        }

        $checksum = hash_file('sha256', $pathName);

        if (! is_string($checksum)) {
            return $this->failure($item, 'checksum_failed', 'The remote file could not be verified.');
        }

        $directory = 'media/imported/'.now()->format('Y/m');
        $publicPath = $existing === null
            ? $directory.'/'.Str::uuid().'.'.$extension
            : $this->restorationPath($existing, $mimeType, $extension);
        $stream = fopen($pathName, 'rb');

        if ($stream === false) {
            return $this->failure($item, 'storage_failed', 'The remote file could not be stored.');
        }

        try {
            $stored = $this->publicDisk->put($publicPath, $stream, ['visibility' => 'public']);
        } catch (FilesystemException) {
            $stored = false;
        } catch (Throwable $exception) {
            if (! $this->cleanupPublicPath($publicPath, 'unexpected_storage_write_failure')) {
                throw new RemoteMediaCleanupFailed($exception);
            }

            throw $exception;
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            if (! $this->cleanupPublicPath($publicPath, 'storage_write_failed')) {
                return $this->cleanupFailure($item);
            }

            return $this->failure($item, 'storage_failed', 'The remote file could not be stored.');
        }

        if ($existing !== null) {
            return $this->finishRestoration(
                item: $item,
                media: $existing,
                path: $publicPath,
                size: $size,
                kind: $kind,
                mimeType: $mimeType,
                dimensions: $dimensions,
                checksum: $checksum,
            );
        }

        try {
            $media = Media::query()->create([
                'path' => $publicPath,
                'original_name' => $this->originalName($item->sourceUrl, $extension),
                'mime_type' => $mimeType,
                'size' => $size,
                'width' => is_array($dimensions) ? $dimensions[0] : null,
                'height' => is_array($dimensions) ? $dimensions[1] : null,
                'alt' => $item->alt,
                'kind' => $kind,
                'title' => $item->title,
                'handle' => $item->handle,
                'source_url' => $item->sourceUrl,
                'source_hash' => $sourceHash,
                'checksum' => $checksum,
            ]);
        } catch (QueryException) {
            if (! $this->cleanupPublicPath($publicPath, 'database_write_failed')) {
                return $this->cleanupFailure($item);
            }

            $existing = Media::query()->where('source_hash', $sourceHash)->first();

            if ($existing !== null) {
                return new MediaImportResult('existing', $item->sourceUrl, $existing);
            }

            if ($item->handle !== null && Media::query()->where('handle', $item->handle)->exists()) {
                return $this->failure(
                    $item,
                    'handle_conflict',
                    'The requested handle is already assigned to another media item.',
                );
            }

            return $this->failure($item, 'database_failed', 'The media catalog could not be updated.');
        } catch (Throwable $exception) {
            if (! $this->cleanupPublicPath($publicPath, 'unexpected_post_write_failure')) {
                throw new RemoteMediaCleanupFailed($exception);
            }

            throw $exception;
        }

        return new MediaImportResult('created', $item->sourceUrl, $media);
    }

    /** @param array<int, int|string>|false $dimensions */
    private function finishRestoration(
        MediaImportItem $item,
        Media $media,
        string $path,
        int $size,
        MediaKind $kind,
        string $mimeType,
        array|false $dimensions,
        string $checksum,
    ): MediaImportResult {
        $previousPath = $media->path;

        try {
            $media->update([
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'width' => is_array($dimensions) ? $dimensions[0] : null,
                'height' => is_array($dimensions) ? $dimensions[1] : null,
                'kind' => $kind,
                'checksum' => $checksum,
            ]);
        } catch (QueryException) {
            if (! $this->cleanupPublicPath($path, 'database_restore_failed')) {
                return $this->cleanupFailure($item);
            }

            return $this->failure($item, 'database_failed', 'The restored media catalog could not be updated.');
        } catch (Throwable $exception) {
            if (! $this->cleanupPublicPath($path, 'unexpected_restore_failure')) {
                throw new RemoteMediaCleanupFailed($exception);
            }

            throw $exception;
        }

        if ($previousPath !== $path && $this->publicDisk->exists($previousPath)) {
            $this->publicDisk->delete($previousPath);
        }

        return new MediaImportResult(
            status: 'existing',
            sourceUrl: $item->sourceUrl,
            media: $media->refresh(),
            message: 'The missing stored file was restored.',
        );
    }

    private function restorationPath(Media $media, string $mimeType, string $extension): string
    {
        if ($media->mime_type === $mimeType) {
            return $media->path;
        }

        $directory = dirname($media->path);
        $prefix = $directory === '.' ? '' : $directory.'/';

        return $prefix.pathinfo($media->path, PATHINFO_FILENAME).'.'.$extension;
    }

    private function normalizeSourceUrl(string $sourceUrl): string
    {
        $uri = (new Uri(trim($sourceUrl)))->withFragment('');

        return (string) UriNormalizer::normalize($uri);
    }

    private function originalName(string $sourceUrl, string $extension): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        $name = is_string($path) ? basename(rawurldecode($path)) : '';

        return $name !== '' ? $name : 'imported.'.$extension;
    }

    private function failure(MediaImportItem $item, string $errorCode, string $message): MediaImportResult
    {
        return new MediaImportResult(
            status: 'failed',
            sourceUrl: $item->sourceUrl,
            errorCode: $errorCode,
            message: $message,
        );
    }

    private function cleanupPublicPath(string $path, string $reason): bool
    {
        try {
            $deleted = $this->publicDisk->delete($path);
        } catch (Throwable $exception) {
            Log::error('Remote media cleanup failed.', [
                'path' => $path,
                'reason' => $reason,
                'exception' => $exception,
            ]);

            return false;
        }

        if (! $deleted) {
            Log::error('Remote media cleanup failed.', [
                'path' => $path,
                'reason' => $reason,
            ]);

            return false;
        }

        return true;
    }

    private function cleanupFailure(MediaImportItem $item): MediaImportResult
    {
        return $this->failure(
            $item,
            'cleanup_failed',
            'The failed import may have left an incomplete stored file.',
        );
    }
}
