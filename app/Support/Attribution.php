<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where a visitor came from, captured on their first page view and kept for
 * the session so it is still known when they eventually submit the form.
 *
 * First touch wins: someone who arrives from an ad and then browses three
 * pages should still be credited to the ad, not to the last internal link they
 * clicked. The one exception is upgrading a visit that started with no source
 * at all, which happens when a session begins on a page without parameters.
 */
class Attribution
{
    public const SESSION_KEY = 'attribution';

    /** Campaign parameters, stored one column per field. */
    public const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /** Advertising click identifiers, which prove a paid click even without UTMs. */
    public const CLICK_FIELDS = ['gclid', 'fbclid', 'msclkid'];

    public const CONTEXT_FIELDS = ['referrer', 'landing_page'];

    /**
     * Everything worth recording from this request.
     *
     * @return array<string, string|null>
     */
    public static function fromRequest(Request $request): array
    {
        $captured = [];

        foreach ([...self::UTM_FIELDS, ...self::CLICK_FIELDS] as $field) {
            $value = $request->query($field);

            $captured[$field] = is_string($value) && trim($value) !== ''
                ? mb_substr(trim($value), 0, 255)
                : null;
        }

        $referrer = $request->headers->get('referer');

        $captured['referrer'] = self::isOffsite($referrer) ? mb_substr($referrer, 0, 512) : null;
        $captured['landing_page'] = mb_substr($request->fullUrl(), 0, 512);

        return $captured;
    }

    /**
     * Whether this request identifies a campaign, as opposed to being an
     * ordinary visit that happens to carry a referrer.
     *
     * @param  array<string, string|null>  $attribution
     */
    public static function hasSource(array $attribution): bool
    {
        foreach ([...self::UTM_FIELDS, ...self::CLICK_FIELDS] as $field) {
            if (filled($attribution[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string|null>
     */
    public static function stored(): array
    {
        $stored = session(self::SESSION_KEY, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param  array<string, string|null>  $attribution
     */
    public static function remember(array $attribution): void
    {
        session([self::SESSION_KEY => $attribution]);
    }

    /**
     * A short description of the origin, for someone reading the lead inbox.
     *
     * @param  array<string, string|null>  $attribution
     */
    public static function describe(array $attribution): string
    {
        if (filled($attribution['gclid'] ?? null)) {
            return 'Google Ads';
        }

        if (filled($attribution['fbclid'] ?? null)) {
            return 'Meta Ads';
        }

        if (filled($attribution['msclkid'] ?? null)) {
            return 'Microsoft Ads';
        }

        if (filled($attribution['utm_source'] ?? null)) {
            $parts = array_filter([
                $attribution['utm_source'],
                $attribution['utm_medium'] ?? null,
            ]);

            return implode(' / ', $parts);
        }

        if (filled($attribution['referrer'] ?? null)) {
            return 'Referral from '.(parse_url($attribution['referrer'], PHP_URL_HOST) ?: 'another site');
        }

        return 'Direct';
    }

    /**
     * Rejects our own pages so internal navigation is never recorded as the
     * referring site.
     */
    private static function isOffsite(?string $referrer): bool
    {
        if (! is_string($referrer) || trim($referrer) === '') {
            return false;
        }

        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        $siteHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $referrerHost !== null && $referrerHost !== $siteHost;
    }
}
