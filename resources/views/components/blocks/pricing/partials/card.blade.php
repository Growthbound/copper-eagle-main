@php
    $data = app(\App\Content\BlockData::class);
    $features = array_values(array_filter(
        (array) ($plan['features'] ?? []),
        fn ($feature): bool => is_string($feature) && trim($feature) !== '',
    ));
    $cta = is_array($plan['cta'] ?? null) ? $plan['cta'] : null;
    $media = is_array($plan['media'] ?? null) ? $plan['media'] : null;
@endphp

<article
    class="pricing-card{{ ! empty($plan['featured']) ? ' pricing-card-featured' : '' }}"
    data-pricing-plan
>
    <div class="pricing-card-heading">
        @if ($media !== null)
            <img
                src="{{ $data->assetUrl($media['src']) }}"
                alt="{{ $media['alt'] ?? '' }}"
                width="{{ $media['width'] }}"
                height="{{ $media['height'] }}"
                loading="lazy"
            >
        @else
            <span class="pricing-card-number" aria-hidden="true">
                {{ str_pad((string) $position, 2, '0', STR_PAD_LEFT) }}
            </span>
        @endif

        <div>
            <h3>{{ $plan['name'] }}</h3>
            @if (filled($plan['summary'] ?? null))
                <p>{{ $plan['summary'] }}</p>
            @endif
        </div>
    </div>

    <div class="pricing-card-prices">
        @foreach ($modes as $mode)
            @php($price = $plan['prices'][$mode])
            <div class="pricing-price" data-price-mode="{{ $mode }}">
                <strong>{{ $price['amount'] }}</strong>
                @if (filled($price['suffix'] ?? null))
                    <span>{{ $price['suffix'] }}</span>
                @endif
                @if (filled($price['note'] ?? null))
                    <small>{{ $price['note'] }}</small>
                @endif
            </div>
        @endforeach
    </div>

    @if (filled($plan['featureHeading'] ?? null) || $features !== [])
        <div class="pricing-card-features">
            @if (filled($plan['featureHeading'] ?? null))
                <h4>{{ $plan['featureHeading'] }}</h4>
            @endif

            @if ($features !== [])
                <ul class="pricing-feature-list">
                    @foreach ($features as $feature)
                        <li>
                            <span aria-hidden="true">✓</span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if (filled($cta['label'] ?? null) && filled($cta['href'] ?? null))
        <a class="pricing-card-cta button button-orange" href="{{ $cta['href'] }}">
            {{ $cta['label'] }}
            <span aria-hidden="true">→</span>
        </a>
    @endif
</article>
