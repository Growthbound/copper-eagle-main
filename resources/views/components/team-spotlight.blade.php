{{--
    Site chrome, not a block: the team spotlight the live site shows above the
    footer on every page. Each person's photo sits beside their name and bio,
    on copper panels over the drain photo (see "Site chrome" in
    docs/brand/design-system.md).

    With no props it reads the team section of the published team page
    through App\Content\TeamSpotlight, so editing that page updates both. The
    gallery preview passes fictional `members` and a `heading` instead. The
    H2 is for assistive technology only, as on the live site, which shows the
    people without a visible heading.
--}}
@props([
    'members' => null,
    'heading' => null,
])

@php
    $spotlightService = app(App\Content\TeamSpotlight::class);
    $spotlight = $members === null
        ? $spotlightService->current()
        : $spotlightService->spotlight((string) $heading, $members);
    $data = app(App\Content\BlockData::class);
@endphp

@if ($spotlight)
    <section class="team-spotlight" aria-labelledby="team-spotlight-title">
        <div class="team-spotlight-inner">
            <h2 id="team-spotlight-title" class="sr-only">{{ $spotlight['heading'] }}</h2>

            <ul class="team-spotlight-list" role="list">
                @foreach ($spotlight['members'] as $person)
                    <li class="team-spotlight-member">
                        <img
                            class="team-spotlight-photo"
                            src="{{ $data->assetUrl($person['image']['src']) }}"
                            alt="{{ $person['image']['alt'] }}"
                            width="{{ $person['image']['width'] }}"
                            height="{{ $person['image']['height'] }}"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="team-spotlight-copy">
                            <h3>{{ $person['name'] }}</h3>
                            @if ($person['bio'] !== null)
                                <p>{{ $person['bio'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
