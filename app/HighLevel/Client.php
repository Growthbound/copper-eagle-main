<?php

namespace App\HighLevel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Client
{
    public function __construct(private readonly string $token) {}

    public function request(string $method, string $path, array $data = []): array
    {
        try {
            $response = Http::baseUrl('https://services.leadconnectorhq.com')
                ->withToken($this->token)->withHeaders(['Version' => 'v3'])->acceptJson()->asJson()
                ->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(10)
                ->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException) {
            // Never persist provider bodies, request details, or exception text.
            throw new Failure('HighLevel did not respond. The delivery will be checked before retrying.', true, $method !== 'GET');
        }

        if (! $response->successful()) {
            $status = $response->status();
            $message = match ($status) {
                401, 403 => 'HighLevel rejected the key or its permissions. Reconnect with the required scopes.',
                429 => 'HighLevel rate limit reached. Delivery will retry later.',
                default => 'HighLevel returned HTTP '.$status.'. Check the connection and lead details.',
            };
            throw new Failure($message, $status === 429 || $status >= 500, $method !== 'GET' && $status >= 500);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new Failure('HighLevel returned an unreadable response.', false, $method !== 'GET');
        }

        return $json;
    }

    public static function identifier(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $value)) {
            throw new Failure('HighLevel returned an invalid record identifier.');
        }

        return $value;
    }
}
