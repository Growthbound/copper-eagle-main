@props([
    'id' => 'team-grid',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'members' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $displayMembers = collect(is_array($members) ? $members : [])
        ->filter(function (mixed $member): bool {
            $image = is_array($member) && is_array($member['image'] ?? null)
                ? $member['image']
                : [];

            return is_array($member)
                && filled($member['name'] ?? null)
                && filled($member['role'] ?? null)
                && filled($image['src'] ?? null)
                && filled($image['alt'] ?? null)
                && is_numeric($image['width'] ?? null)
                && is_numeric($image['height'] ?? null)
                && (int) $image['width'] > 0
                && (int) $image['height'] > 0;
        })
        ->values();

    $isSafeProfileUrl = static function (mixed $url): bool {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true);
    };
@endphp

<section
    id="{{ $id }}"
    @class([
        'team-grid-section',
        'section-space',
        'is-empty' => $displayMembers->isEmpty(),
    ])
    data-team-variant="grid"
    aria-labelledby="{{ $id }}-title"
>
    <header class="team-grid-heading">
        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif
    </header>

    @if ($displayMembers->isNotEmpty())
        <div class="team-grid">
            @foreach ($displayMembers as $member)
                @php
                    $image = $member['image'];
                    $linkedinUrl = $member['linkedin_url'] ?? null;
                    $hasLinkedIn = $isSafeProfileUrl($linkedinUrl);
                @endphp

                <article class="team-card">
                    <img
                        class="team-card-portrait"
                        src="{{ $data->assetUrl($image['src']) }}"
                        alt="{{ $image['alt'] }}"
                        width="{{ (int) $image['width'] }}"
                        height="{{ (int) $image['height'] }}"
                        loading="lazy"
                    >

                    <div class="team-card-meta">
                        <div>
                            <h3>{{ $member['name'] }}</h3>
                            <p>{{ $member['role'] }}</p>
                        </div>

                        @if ($hasLinkedIn)
                            <a
                                class="team-card-link"
                                href="{{ $linkedinUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="View {{ $member['name'] }} on LinkedIn"
                            ><span aria-hidden="true">in</span></a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
