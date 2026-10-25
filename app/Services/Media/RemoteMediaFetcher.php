<?php

namespace App\Services\Media;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use SplFileInfo;
use Throwable;

class RemoteMediaFetcher
{
    private readonly ClientInterface $client;

    private readonly int $maxBytes;

    private readonly int $connectTimeout;

    private readonly int $timeout;

    private readonly int $maxRedirects;

    public function __construct(
        private readonly RemoteUrlGuard $guard,
        ?ClientInterface $client = null,
        ?int $maxBytes = null,
        ?int $connectTimeout = null,
        ?int $timeout = null,
        ?int $maxRedirects = null,
    ) {
        $this->client = $client ?? new Client;
        $this->maxBytes = $maxBytes ?? (int) config('media-import.max_bytes');
        $this->connectTimeout = $connectTimeout ?? (int) config('media-import.connect_timeout');
        $this->timeout = $timeout ?? (int) config('media-import.timeout');
        $this->maxRedirects = $maxRedirects ?? (int) config('media-import.max_redirects');
    }

    public function fetch(string $url): SplFileInfo
    {
        $directory = storage_path('app/private/media-imports');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid().'.tmp';
        $currentUrl = $url;
        $redirects = 0;

        try {
            while (true) {
                $validatedAddresses = $this->guard->resolvePublicAddresses($currentUrl);

                $response = $this->client->request('GET', $currentUrl, [
                    'allow_redirects' => false,
                    'connect_timeout' => $this->connectTimeout,
                    'timeout' => $this->timeout,
                    'http_errors' => false,
                    'sink' => $path,
                    'curl' => [
                        CURLOPT_RESOLVE => [$this->pinnedAddress($currentUrl, $validatedAddresses[0])],
                    ],
                    'on_headers' => function (ResponseInterface $response): void {
                        $contentLength = $response->getHeaderLine('Content-Length');

                        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $this->maxBytes) {
                            throw new RemoteMediaTooLarge;
                        }
                    },
                    'progress' => function (
                        int $downloadTotal,
                        int $downloadedBytes,
                        int $uploadTotal,
                        int $uploadedBytes,
                    ): void {
                        if ($downloadTotal > $this->maxBytes || $downloadedBytes > $this->maxBytes) {
                            throw new RemoteMediaTooLarge;
                        }
                    },
                ]);

                if ($this->isRedirect($response)) {
                    if ($redirects >= $this->maxRedirects) {
                        throw new RemoteMediaFetchFailed(
                            errorCode: 'too_many_redirects',
                            safeMessage: 'The remote URL redirected too many times.',
                        );
                    }

                    $location = $response->getHeaderLine('Location');

                    if ($location === '') {
                        throw new RemoteMediaFetchFailed;
                    }

                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
                    $redirects++;

                    continue;
                }

                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    throw new RemoteMediaFetchFailed;
                }

                clearstatcache(true, $path);

                if (! is_file($path)) {
                    throw new RemoteMediaFetchFailed;
                }

                if (filesize($path) > $this->maxBytes) {
                    throw new RemoteMediaTooLarge;
                }

                return new SplFileInfo($path);
            }
        } catch (Throwable $exception) {
            File::delete($path);

            $knownException = $this->knownException($exception);

            if ($knownException !== null) {
                throw $knownException;
            }

            throw new RemoteMediaFetchFailed(previous: $exception);
        }
    }

    private function isRedirect(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 300 && $response->getStatusCode() < 400;
    }

    private function pinnedAddress(string $url, string $address): string
    {
        $uri = new Uri($url);
        $host = $uri->getHost();
        $port = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);
        $curlAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        return $host.':'.$port.':'.$curlAddress;
    }

    private function knownException(Throwable $exception): UnsafeRemoteUrl|RemoteMediaFetchFailed|null
    {
        do {
            if ($exception instanceof UnsafeRemoteUrl || $exception instanceof RemoteMediaFetchFailed) {
                return $exception;
            }

            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return null;
    }
}
