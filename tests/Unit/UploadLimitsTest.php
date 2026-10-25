<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PHP defaults to a 2MB upload ceiling, which is smaller than an ordinary phone
 * photo. When that ceiling was in force the media library reported "must be an
 * image" for perfectly valid files, because PHP discarded the upload before
 * Laravel could inspect it.
 *
 * These assertions keep the server ceiling above the limit the application
 * advertises, so an oversized file fails the application's own validation and
 * produces a message that says what is actually wrong.
 */
class UploadLimitsTest extends TestCase
{
    private const ADVERTISED_LIMIT_MB = 25;

    public function test_both_images_raise_php_upload_limits_above_the_advertised_size(): void
    {
        $ini = $this->contents('docker/php.ini');

        $this->assertGreaterThan(
            self::ADVERTISED_LIMIT_MB,
            $this->megabytes($ini, 'upload_max_filesize'),
            'upload_max_filesize must exceed the size the media library accepts.',
        );

        $this->assertGreaterThan(
            self::ADVERTISED_LIMIT_MB,
            $this->megabytes($ini, 'post_max_size'),
            'post_max_size must exceed a single upload, with room for multipart overhead.',
        );

        foreach (['Dockerfile', 'Dockerfile.production'] as $dockerfile) {
            $this->assertStringContainsString(
                'COPY docker/php.ini /usr/local/etc/php/conf.d/copper-eagle.ini',
                $this->contents($dockerfile),
                $dockerfile.' does not install the upload limits, so uploads will fail there.',
            );
        }
    }

    public function test_the_upload_endpoint_and_the_media_library_agree_on_the_limit(): void
    {
        $livewire = $this->contents('config/livewire.php');

        $this->assertMatchesRegularExpression(
            '/\'rules\'\s*=>\s*\[[^\]]*max:'.(self::ADVERTISED_LIMIT_MB * 1024).'[^\]]*\]/',
            $livewire,
            'Livewire\'s temporary upload limit must match the media library, or files between the two hang with no error.',
        );

        $this->assertStringContainsString(
            'max:'.(self::ADVERTISED_LIMIT_MB * 1024),
            $this->contents('app/Livewire/Admin/MediaLibrary.php'),
        );
    }

    private function megabytes(string $ini, string $directive): int
    {
        preg_match('/^'.preg_quote($directive, '/').'\s*=\s*(\d+)M/mi', $ini, $matches);

        $this->assertNotEmpty($matches, $directive.' is not set in megabytes.');

        return (int) $matches[1];
    }

    private function contents(string $path): string
    {
        $absolute = dirname(__DIR__, 2).'/'.$path;

        $this->assertFileExists($absolute);

        return file_get_contents($absolute);
    }
}
