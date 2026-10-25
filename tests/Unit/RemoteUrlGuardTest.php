<?php

namespace Tests\Unit;

use App\Services\Media\RemoteUrlGuard;
use App\Services\Media\UnsafeRemoteUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteUrlGuardTest extends TestCase
{
    public function test_public_http_and_https_urls_are_allowed(): void
    {
        $guard = new RemoteUrlGuard(fn (string $host): array => ['93.184.216.34']);

        $guard->assertPublicHttpUrl('http://media.example/image.jpg');
        $guard->assertPublicHttpUrl('https://media.example/video.mp4');

        $this->addToAssertionCount(1);
    }

    public function test_public_literal_ipv4_and_ipv6_urls_are_allowed_without_dns(): void
    {
        $guard = new RemoteUrlGuard(function (string $host): array {
            $this->fail("Literal IP address {$host} must not be resolved through DNS.");
        });

        $guard->assertPublicHttpUrl('https://93.184.216.34/image.jpg');
        $guard->assertPublicHttpUrl('https://[2606:4700:4700::1111]/image.jpg');

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_non_absolute_or_unsupported_urls_are_rejected(string $url): void
    {
        $guard = new RemoteUrlGuard(fn (string $host): array => ['93.184.216.34']);

        $this->expectException(UnsafeRemoteUrl::class);

        $guard->assertPublicHttpUrl($url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrlProvider(): array
    {
        return [
            'relative URL' => ['/image.jpg'],
            'missing host' => ['https:///image.jpg'],
            'FTP' => ['ftp://media.example/image.jpg'],
            'file URL' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('unsafeTargetProvider')]
    public function test_private_reserved_and_local_targets_are_rejected(string $url): void
    {
        $guard = new RemoteUrlGuard(fn (string $host): array => ['93.184.216.34']);

        $this->expectException(UnsafeRemoteUrl::class);

        $guard->assertPublicHttpUrl($url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeTargetProvider(): array
    {
        return [
            'localhost' => ['http://localhost/file.jpg'],
            'IPv4 loopback' => ['http://127.0.0.1/file.jpg'],
            'IPv4 private class A' => ['http://10.0.0.4/file.jpg'],
            'IPv4 private class B' => ['http://172.16.2.4/file.jpg'],
            'IPv4 private class C' => ['http://192.168.1.4/file.jpg'],
            'IPv4 link-local' => ['http://169.254.169.254/file.jpg'],
            'IPv4 shared address space' => ['http://100.64.0.1/file.jpg'],
            'IPv4 documentation range' => ['http://192.0.2.1/file.jpg'],
            'IPv4 benchmarking range' => ['http://198.18.0.1/file.jpg'],
            'IPv4 multicast' => ['http://224.0.0.1/file.jpg'],
            'IPv6 loopback' => ['http://[::1]/file.jpg'],
            'IPv6 private' => ['http://[fc00::1]/file.jpg'],
            'IPv6 link-local' => ['http://[fe80::1]/file.jpg'],
            'IPv6 documentation range' => ['http://[2001:db8::1]/file.jpg'],
            'IPv6 multicast' => ['http://[ff02::1]/file.jpg'],
        ];
    }

    public function test_hostname_resolving_to_a_private_address_is_rejected(): void
    {
        $guard = new RemoteUrlGuard(fn (string $host): array => ['10.0.0.4']);

        $this->expectException(UnsafeRemoteUrl::class);

        $guard->assertPublicHttpUrl('https://internal.example/file.jpg');
    }

    public function test_every_resolved_address_must_be_public(): void
    {
        $guard = new RemoteUrlGuard(fn (string $host): array => [
            '93.184.216.34',
            'fd00::10',
        ]);

        $this->expectException(UnsafeRemoteUrl::class);

        $guard->assertPublicHttpUrl('https://mixed.example/file.jpg');
    }

    public function test_hostname_without_resolved_addresses_is_rejected(): void
    {
        $guard = new RemoteUrlGuard(fn (string $host): array => []);

        $this->expectException(UnsafeRemoteUrl::class);

        $guard->assertPublicHttpUrl('https://missing.example/file.jpg');
    }
}
