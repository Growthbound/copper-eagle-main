<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Schema;

/**
 * The site's logo, resolved once for everything that needs to point at it.
 *
 * Three surfaces want this image and had no shared answer: the header mark, the
 * `logo` and `image` fields on the business entity, and the Open Graph card.
 * Left separate they drift, and a business whose logo differs between its
 * header, its structured data, and its link previews is harder for Google to
 * resolve into one entity, not easier.
 *
 * Resolution is the owner's uploaded logo, then nothing. Nothing is a real
 * answer: the site shows its name as text, and structured data declares no
 * logo rather than a placeholder that is not the brand's mark.
 */
final class SiteLogo
{
    /**
     * @var array{url: string, width: int, height: int}|null
     */
    private static ?array $resolved = null;

    private static ?string $resolvedFor = null;

    /**
     * Only for tests that change the configured logo mid-process.
     */
    public static function flush(): void
    {
        self::$resolved = null;
        self::$resolvedFor = null;
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    public static function resolve(): ?array
    {
        /*
         * Four surfaces ask for this on a single page render — the header mark,
         * the footer mark, the business entity and the share card — and with an
         * uploaded logo each one would otherwise cost a table check and a
         * lookup.
         *
         * Keyed on the settings it derives from rather than cached outright,
         * because the owner edits both from the admin and `ApplySiteDetails`
         * rewrites them per request. A flat memo would serve one tenant's
         * wordmark to the next request that changed it.
         */
        $key = json_encode([config('site.logo_media_id'), config('site.name')]);

        if (self::$resolvedFor === $key) {
            return self::$resolved;
        }

        self::$resolvedFor = $key;

        return self::$resolved = self::lookUp();
    }

    /**
     * The logo structured data may declare, which is a different asset from the
     * one the header shows.
     *
     * Google's Organization logo has a floor of 112x112, and a wordmark 80
     * pixels tall does not clear it — declaring one produces markup that is
     * simply ignored, which is worse than declaring nothing because it reads as
     * done. The square app mark qualifies where the wordmark cannot.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public static function forSchema(): ?array
    {
        $logo = self::resolve();

        if ($logo !== null && $logo['width'] >= 112 && $logo['height'] >= 112) {
            return $logo;
        }

        return null;
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    private static function lookUp(): ?array
    {
        $mediaId = config('site.logo_media_id');

        /*
         * Guarded because structured data renders on every page, including
         * before migrations have run and in the in-memory database the content
         * checker uses, where this table does not exist.
         */
        if (filled($mediaId) && Schema::hasTable('media')) {
            $logo = Media::query()->find($mediaId);

            if ($logo !== null) {
                return [
                    'url' => $logo->url(),
                    'width' => (int) $logo->width,
                    'height' => (int) $logo->height,
                ];
            }
        }

        return null;
    }
}
