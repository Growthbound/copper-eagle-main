<?php

/*
|--------------------------------------------------------------------------
| Search visibility
|--------------------------------------------------------------------------
|
| Deployment facts about which copy of this site the public is meant to find.
| Deliberately not in config/site.php: that file is business identity the owner
| edits from the admin, and this is infrastructure. An owner who could change
| the canonical host from a web form could de-index the site, or publish a
| staging copy, without ever seeing what went wrong.
|
*/

return [

    /*
     * The one hostname allowed into search indexes and AI training corpora.
     * Every other host serving this application — staging, preview deploys,
     * container hostnames, raw IPs — is held back.
     *
     * A bare hostname. No scheme, no path, no port, no trailing slash:
     * "example.com", not "https://example.com/". Caddy compares
     * this value against the request host literally when it serves a static
     * file without going through PHP, and it cannot normalize anything, so a
     * value the two read differently would put noindex on production's own
     * images. Anything else is refused outright rather than repaired, and a
     * refused value is treated as unset — nothing is indexable. Boot also
     * fails if this disagrees with the host in APP_URL.
     *
     * Held back does not mean blocked. Search crawlers are let in, because a
     * crawler that cannot fetch a page never sees the noindex on it and leaves
     * URLs it already holds stuck in the index. They get "Allow: /", a noindex
     * header on every response, and no sitemap. Only the answer-engine
     * crawlers listed below get "Disallow: /", since a training corpus has no
     * retraction and refusing before the fetch is the only moment that works.
     *
     * Unset means nothing is indexable, and that is the safe direction. An
     * unlaunched site missing from Google is a loud, one-variable fix; a
     * staging copy quietly competing with production for its own queries takes
     * weeks to undo.
     */
    'canonical_host' => env('SITE_CANONICAL_HOST'),

    /*
     * Crawlers that feed answer engines and training corpora rather than a
     * search index.
     *
     * These are blocked outright on a non-canonical host. The distinction
     * matters: a search engine has to be allowed in to see `noindex` and drop
     * a URL it already holds, but there is no equivalent retraction for a
     * corpus, so the only useful moment to refuse is before the fetch.
     */
    'answer_engine_crawlers' => [
        'GPTBot',
        'ChatGPT-User',
        'OAI-SearchBot',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'anthropic-ai',
        'Google-Extended',
        'Gemini-Deep-Research',
        'PerplexityBot',
        'Perplexity-User',
        'Bytespider',
        'CCBot',
        'Meta-ExternalAgent',
        'FacebookBot',
        'Amazonbot',
        'Applebot-Extended',
        'cohere-ai',
        'DuckAssistBot',
        'GrokBot',
        'AI2Bot',
        'YouBot',
        'PetalBot',
    ],

];
