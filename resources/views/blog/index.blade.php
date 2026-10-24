@php($siteName = trim((string) config('site.name')))
<x-layouts.marketing
    :title="$siteName.' Learning Center'"
    :description="'Articles and practical guidance from '.$siteName.'.'"
    :canonical="rtrim(url('/'), '/').'/blog/'"
    :schema="[[
        '@type' => 'CollectionPage',
        'name' => $siteName.' Learning Center',
        'description' => 'Articles and practical guidance from '.$siteName.'.',
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
    <x-site-header />

    <section class="section">
        <div class="container">
            <p class="eyebrow">Learning center</p>
            <h1>Articles and guides</h1>
            <p class="lede">Practical answers to common questions, written to help you make a confident decision.</p>

            @if ($articles->isEmpty())
                <p>No articles are currently published.</p>
            @else
                <div class="card-grid" aria-label="{{ $siteName }} articles">
                    @foreach ($articles as $article)
                        <article class="card">
                            <p class="eyebrow">Guide</p>
                            <h2><a href="{{ $article->url() }}">{{ $article->title }}</a></h2>
                            @if ($article->publishedAt())
                                <time datetime="{{ $article->publishedAt() }}">{{ \Carbon\CarbonImmutable::parse($article->publishedAt())->format('F j, Y') }}</time>
                            @endif
                            @if ($article->description)
                                <p>{{ $article->description }}</p>
                            @endif
                            <a href="{{ $article->url() }}" aria-label="Read {{ $article->title }}">Read guide</a>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.marketing>
