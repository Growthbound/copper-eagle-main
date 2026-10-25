<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * Whether the host currently serving this request is allowed into search
 * indexes and AI training corpora.
 *
 * A staging copy of a marketing site is a complete, self-canonicalizing
 * duplicate of the real one. Left open it competes with production for its own
 * queries, splits the entity across two hostnames, and gets ingested by model
 * crawlers that will keep repeating it long after the host is gone.
 *
 * The check is the request host rather than `APP_ENV`, because staging is
 * routinely deployed with `APP_ENV=production` — the environment name is a
 * statement about how the app should behave, not about which hostname the
 * public is meant to find. The hostname is the thing that actually differs.
 *
 * It fails closed. With `SITE_CANONICAL_HOST` unset nothing is indexable, so a
 * forgotten variable costs visibility on a site nobody has launched yet rather
 * than leaking one that was never meant to be found. Production sets the
 * variable once; the failure is loud, immediate, and fixable without a deploy.
 */
final class SearchVisibility
{
    public static function canonicalHost(): ?string
    {
        $host = config('search.canonical_host');

        if (! is_string($host)) {
            return null;
        }

        /*
         * A bare hostname only: no scheme, no path, no port. Caddy serves
         * static files without going through PHP, so it compares this value
         * against the request host literally and cannot normalize anything.
         * Accepting a full URL here made the two consumers disagree: Laravel
         * would treat production as canonical while Caddy marked it
         * non-canonical and stamped noindex on its own images.
         *
         * A port is refused for the same reason, one layer down. Nothing this
         * value is compared against carries one — `Request::getHost()` strips
         * it, and so does `parse_url(..., PHP_URL_HOST)` in the APP_URL check.
         * So `localhost:8011` could never match anything: boot would abort
         * even against `APP_URL=http://localhost:8011`, and past that the site
         * would sit noindexed with a value that looks exactly right.
         *
         * One format, understood the same way everywhere, is worth more than
         * being forgiving in any single place.
         */
        $normalized = strtolower(trim($host));

        if ($normalized === '' || preg_match('#^[a-z0-9.-]+$#', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    public static function indexable(): bool
    {
        $canonical = self::canonicalHost();

        if ($canonical === null) {
            return false;
        }

        return strtolower(Request::getHost()) === $canonical;
    }
}
