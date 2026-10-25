{{--
    The blog index, laid out like the live blog: a "Blog" hero, then one row
    per post with its photo (the post's social image), author, date, title and
    a "Read More" link. Dates read like the live index ("9/8/23") with the ISO
    date in <time datetime>.
--}}
@php
    $siteName = trim((string) config('site.name'));
    $description = 'Explore expert advice, updates, and tips from '.$siteName.'. Stay informed with our latest blog posts and service insights.';
    $data = app(\App\Content\BlockData::class);
@endphp
<x-layouts.marketing
    title="Blog | Learn More & Stay Informed"
    :description="$description"
    :canonical="rtrim(url('/'), '/').'/blog/'"
    :schema="[[
        '@type' => 'CollectionPage',
        'name' => 'Blog',
        'description' => $description,
        'url' => rtrim(url('/'), '/').'/blog/',
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => $articles->count(),
            'itemListElement' => $articles->values()->map(fn ($article, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $article->title,
                'url' => $article->canonicalUrl(),
            ])->all(),
        ],
    ]]"
>
    <x-blocks.hero.interior id="blog-hero" heading="Blog" />

    <section class="blog-index section-space" aria-label="{{ $siteName }} blog posts">
        @forelse ($articles as $article)
            @php
                $hero = collect($article->blocks)->first(fn ($block) => $block->type === 'hero/article');
                $author = is_string($hero?->props['author'] ?? null) ? trim($hero->props['author']) : '';
                $publishedAt = $data->date($article->publishedAt());
                $image = $article->socialImage;
                // The live index lists each post under its own headline.
                $headline = is_string($hero?->props['heading'] ?? null) && trim($hero->props['heading']) !== ''
                    ? trim($hero->props['heading'])
                    : $article->title;
            @endphp
            <article @class(['blog-index-item', 'has-image' => $image !== null])>
                @if ($image)
                    <a class="blog-index-media" href="{{ $article->url() }}" tabindex="-1" aria-hidden="true">
                        <img
                            src="{{ $data->assetUrl($image['src']) }}"
                            alt="{{ $image['alt'] }}"
                            width="{{ $image['width'] }}"
                            height="{{ $image['height'] }}"
                            loading="lazy"
                        >
                    </a>
                @endif

                <div class="blog-index-summary">
                    @if ($author !== '' || $publishedAt)
                        <p class="blog-index-meta">
                            @if ($author !== '')
                                <span>{{ $author }}</span>
                            @endif
                            @if ($publishedAt)
                                <time datetime="{{ $publishedAt }}">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $publishedAt)->format('n/j/y') }}</time>
                            @endif
                        </p>
                    @endif
                    <h2 class="blog-index-title"><a href="{{ $article->url() }}">{{ $headline }}</a></h2>
                    <a class="blog-index-more" href="{{ $article->url() }}" aria-label="Read More: {{ $headline }}">Read More</a>
                </div>
            </article>
        @empty
            <p>No articles are currently published.</p>
        @endforelse
    </section>
</x-layouts.marketing>
