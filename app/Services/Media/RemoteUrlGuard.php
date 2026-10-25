<?php

namespace App\Services\Media;

use Closure;

class RemoteUrlGuard
{
    /** @var array<int, string> */
    private const RESERVED_NETWORKS = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
    ];

    /** @var Closure(string): array<int, string> */
    private readonly Closure $resolver;

    /**
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver === null
            ? $this->resolveHost(...)
            : Closure::fromCallable($resolver);
    }

    public function assertPublicHttpUrl(string $url): void
    {
        $this->resolvePublicAddresses($url);
    }

    /**
     * @return array<int, string>
     */
    public function resolvePublicAddresses(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new UnsafeRemoteUrl;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $host === 'localhost') {
            throw new UnsafeRemoteUrl;
        }

        $addressHost = trim($host, '[]');
        $addresses = filter_var($addressHost, FILTER_VALIDATE_IP) !== false
            ? [$addressHost]
            : ($this->resolver)($addressHost);

        if ($addresses === []) {
            throw new UnsafeRemoteUrl;
        }

        foreach ($addresses as $address) {
            if (! is_string($address) || ! $this->isPublicAddress($address)) {
                throw new UnsafeRemoteUrl;
            }
        }

        return $addresses;
    }

    private function isPublicAddress(string $address): bool
    {
        if ($this->isMulticastAddress($address) || $this->isReservedAddress($address)) {
            return false;
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function isMulticastAddress(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        return strlen($packed) === 4
            ? (ord($packed[0]) & 0xF0) === 0xE0
            : ord($packed[0]) === 0xFF;
    }

    private function isReservedAddress(string $address): bool
    {
        foreach (self::RESERVED_NETWORKS as $network) {
            if ($this->isInNetwork($address, $network)) {
                return true;
            }
        }

        return false;
    }

    private function isInNetwork(string $address, string $network): bool
    {
        [$subnet, $prefixLength] = explode('/', $network, 2);
        $packedAddress = @inet_pton($address);
        $packedSubnet = @inet_pton($subnet);

        if ($packedAddress === false || $packedSubnet === false || strlen($packedAddress) !== strlen($packedSubnet)) {
            return false;
        }

        $prefix = (int) $prefixLength;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($wholeBytes > 0 && substr($packedAddress, 0, $wholeBytes) !== substr($packedSubnet, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedAddress[$wholeBytes]) & $mask) === (ord($packedSubnet[$wholeBytes]) & $mask);
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
