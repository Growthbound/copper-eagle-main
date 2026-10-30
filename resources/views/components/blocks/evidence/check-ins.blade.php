{{--
    Job check-ins the team posts: who did the work, the day, what they did,
    and photos from the job, newest first. They are the team's own write-ups,
    not customer reviews, so nothing here feeds review markup.

    Each check-in is a card with the write-up on the start side and a photo
    strip on the end side; several photos scroll sideways, with a square
    button per photo. Two check-ins show at first and "Load more check-ins"
    reveals two more at a time. Without JavaScript every check-in shows and
    the photo strip scrolls by touch or trackpad.
--}}
@props([
    'id' => 'check-ins',
    'eyebrow' => null,
    'heading',
    'items' => [],
])

@php
    $data = app(\App\Content\BlockData::class);

    $checkIns = collect(is_array($items) ? $items : [])
        ->map(function (mixed $item) use ($data): ?array {
            if (! is_array($item) || ! filled($item['author'] ?? null) || ! is_string($item['date'] ?? null)) {
                return null;
            }

            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $item['date']);

            if ($date === false || $date->format('Y-m-d') !== $item['date']) {
                return null;
            }

            $author = trim((string) $item['author']);
            $words = preg_split('/\s+/u', $author) ?: [];

            return [
                'author' => $author,
                'initials' => mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : '')),
                'date' => $date,
                'body' => is_string($item['body'] ?? null) ? $item['body'] : null,
                'photos' => collect(is_array($item['photos'] ?? null) ? $item['photos'] : [])
                    ->map(fn (mixed $photo): ?array => $data->media($photo))
                    ->filter()
                    ->take(8)
                    ->values(),
            ];
        })
        ->filter(fn (?array $item): bool => $item !== null && (filled($item['body']) || $item['photos']->isNotEmpty()))
        ->take(60)
        ->values();
@endphp

@if ($checkIns->isNotEmpty())
    <section id="{{ $id }}" class="check-ins section-space" aria-labelledby="{{ $id }}-title" data-check-ins data-check-ins-batch="2">
        <div class="section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif

            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </div>

        <ol class="check-ins-list" id="{{ $id }}-list" role="list">
            @foreach ($checkIns as $checkIn)
                <li @class(['check-in', 'is-text-only' => $checkIn['photos']->isEmpty()]) data-check-in tabindex="-1">
                    <div class="check-in-copy">
                        <p class="check-in-byline">
                            <span class="check-in-initials" aria-hidden="true">{{ $checkIn['initials'] }}</span>
                            <span class="check-in-meta">
                                <span class="check-in-author">{{ $checkIn['author'] }}</span>
                                <time datetime="{{ $checkIn['date']->format('Y-m-d') }}">{{ $checkIn['date']->format('F j, Y') }}</time>
                            </span>
                        </p>

                        @if (filled($checkIn['body']))
                            <div class="check-in-body">
                                <x-content.paragraphs :text="$checkIn['body']" />
                            </div>
                        @endif
                    </div>

                    @if ($checkIn['photos']->isNotEmpty())
                        <div class="check-in-photos" data-check-in-photos>
                            <ul class="check-in-photo-track" role="list" data-check-in-photo-track>
                                @foreach ($checkIn['photos'] as $photo)
                                    <li class="check-in-photo">
                                        <img
                                            src="{{ $photo['src'] }}"
                                            alt="{{ $photo['alt'] }}"
                                            width="{{ $photo['width'] }}"
                                            height="{{ $photo['height'] }}"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </li>
                                @endforeach
                            </ul>

                            @if ($checkIn['photos']->count() > 1)
                                <div class="check-in-photo-dots" data-check-in-photo-dots hidden>
                                    @foreach ($checkIn['photos'] as $index => $photo)
                                        <button
                                            type="button"
                                            class="check-in-photo-dot"
                                            data-check-in-photo-dot="{{ $index }}"
                                            aria-label="Show photo {{ $index + 1 }} of {{ $checkIn['photos']->count() }}"
                                            @if ($index === 0) aria-current="true" @endif
                                        ></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        <p class="check-ins-more" data-check-ins-more hidden>
            <button type="button" class="button button-outline" aria-controls="{{ $id }}-list" data-check-ins-more-button>
                Load more check-ins
            </button>
        </p>
    </section>
@endif
