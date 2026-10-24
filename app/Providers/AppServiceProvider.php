<?php

namespace App\Providers;

use App\Support\SearchVisibility;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertCanonicalHostMatchesAppUrl();

        RateLimiter::for('content-api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->credentialKey($request)));
        RateLimiter::for('media-import-api', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->credentialKey($request)));
    }

    /**
     * The canonical host and APP_URL have to name the same site.
     *
     * They drive different things: the canonical host decides which responses
     * are indexable, while APP_URL builds the sitemap and the URLs inside it.
     * Let them disagree and the canonical host serves a permissive robots file
     * advertising a sitemap full of the other host's URLs — pointing search
     * engines at pages that are themselves noindexed. Nothing else in the
     * system would notice.
     */
    private function assertCanonicalHostMatchesAppUrl(): void
    {
        $canonical = SearchVisibility::canonicalHost();

        if ($canonical === null) {
            return;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($appHost !== '' && $appHost !== $canonical) {
            throw new RuntimeException(
                "SITE_CANONICAL_HOST ({$canonical}) does not match the host in APP_URL ({$appHost}). "
                .'They must name the same site, or the canonical host will advertise a sitemap of another host'."'s URLs.",
            );
        }
    }

    private function credentialKey(Request $request): string
    {
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey !== null && method_exists($apiKey, 'getKey')) {
            return 'api-key:'.$apiKey->getKey();
        }

        // Deliberately use the socket peer, not forwarded headers. Trusted
        // proxy metadata is necessary for canonical HTTPS URLs but must not
        // let a caller manufacture fresh rate-limit buckets. Failed Basic
        // credentials are guesses, not stable identities, so they never own
        // separate buckets.
        return 'peer:'.(string) $request->server('REMOTE_ADDR', 'unknown');
    }
}
