<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The business's Google rating, as an aggregate.
 *
 * Deliberately the rating and the count, not the individual reviews. The
 * Places API returns at most five reviews chosen by Google with no way to
 * filter them, and cherry-picking a public review feed to hide the bad ones is
 * what the FTC's rule on review suppression prohibits. An honest "4.8 from 127
 * reviews" linking to the real profile is both compliant and more persuasive
 * than four suspiciously perfect quotes.
 *
 * The last successful figures are persisted, so a Google outage or an expired
 * key leaves the site showing the previous rating rather than nothing.
 */
class GoogleReviews
{
    public const PLACE_ID = 'google.place_id';

    public const API_KEY = 'google.places_api_key';

    private const RATING = 'google.rating';

    private const COUNT = 'google.rating_count';

    private const URL = 'google.maps_uri';

    private const FETCHED_AT = 'google.fetched_at';

    /** How long a stored rating is treated as current. */
    private const FRESH_FOR_HOURS = 24;

    public static function isConfigured(): bool
    {
        return filled(Setting::read(self::PLACE_ID)) && filled(Setting::read(self::API_KEY));
    }

    /**
     * What to display right now: the last figures fetched, if any.
     *
     * @return array{rating: float, count: int, url: string|null}|null
     */
    public static function current(): ?array
    {
        $rating = Setting::read(self::RATING);
        $count = Setting::read(self::COUNT);

        if ($rating === null || $count === null) {
            return null;
        }

        return [
            'rating' => round((float) $rating, 1),
            'count' => (int) $count,
            'url' => Setting::read(self::URL),
        ];
    }

    public static function isStale(): bool
    {
        $fetchedAt = Setting::read(self::FETCHED_AT);

        return $fetchedAt === null
            || now()->diffInHours(Carbon::parse($fetchedAt), absolute: true) >= self::FRESH_FOR_HOURS;
    }

    /**
     * Asks Google for the current rating and stores it.
     *
     * @return array{ok: bool, message: string}
     */
    public static function refresh(): array
    {
        $placeId = Setting::read(self::PLACE_ID);
        $apiKey = Setting::read(self::API_KEY);

        if (blank($placeId) || blank($apiKey)) {
            return ['ok' => false, 'message' => 'Add both a Place ID and an API key first.'];
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'rating,userRatingCount,googleMapsUri,displayName',
            ])
                ->timeout(8)
                ->get('https://places.googleapis.com/v1/places/'.urlencode($placeId));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Google: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Google answered '.$response->status().'. Check the Place ID and that the key allows the Places API.',
            ];
        }

        $rating = $response->json('rating');
        $count = $response->json('userRatingCount');

        if (! is_numeric($rating) || ! is_numeric($count)) {
            return ['ok' => false, 'message' => 'Google returned no rating for that Place ID yet.'];
        }

        Setting::write([
            self::RATING => (string) $rating,
            self::COUNT => (string) $count,
            self::URL => $response->json('googleMapsUri'),
            self::FETCHED_AT => now()->toIso8601String(),
        ]);

        return [
            'ok' => true,
            'message' => sprintf('Google reports %s from %d reviews.', round((float) $rating, 1), (int) $count),
        ];
    }

    public static function save(string $placeId, string $apiKey): void
    {
        Setting::write([
            self::PLACE_ID => trim($placeId) === '' ? null : trim($placeId),
            self::API_KEY => trim($apiKey) === '' ? null : trim($apiKey),
        ]);
    }

    public static function forget(): void
    {
        Setting::erase(self::RATING, self::COUNT, self::URL, self::FETCHED_AT);
    }
}
