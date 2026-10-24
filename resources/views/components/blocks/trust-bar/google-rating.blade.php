{{--
    The business's real Google rating, pulled from their profile and shown in
    full: the average and the total number of reviews, linking to the profile
    so anyone can read them all — including the ones that are not five stars.
--}}
@props([
    'heading' => 'Rated on Google',
])

@php($rating = App\Support\GoogleReviews::current())

@if ($rating)
    <section class="google-rating" aria-label="{{ $heading }}">
        <p class="google-rating-score">
            <span class="google-rating-stars" aria-hidden="true">{{ str_repeat('★', (int) round($rating['rating'])) }}</span>
            <strong>{{ number_format($rating['rating'], 1) }}</strong>
            <span>out of 5 from {{ number_format($rating['count']) }} Google reviews</span>
        </p>

        @if ($rating['url'])
            <a class="text-link" href="{{ $rating['url'] }}" rel="noopener" target="_blank">Read them on Google</a>
        @endif
    </section>
@endif
