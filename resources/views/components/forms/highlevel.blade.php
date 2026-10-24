@props(['embed'])

<div class="website-form-embed" data-website-form="highlevel">
    <iframe
        src="{{ $embed['src'] }}"
        id="{{ $embed['iframeId'] }}"
        title="{{ $embed['title'] }}"
        data-form-id="{{ $embed['formId'] }}"
        height="1000"
        referrerpolicy="strict-origin"
    ></iframe>
    <p class="website-form-alternative">
        Having trouble with the form?
        <a href="{{ $embed['src'] }}" target="_blank" rel="noopener noreferrer">Open the form in a new tab</a>
        @if (filled(config('site.phone')))
            or <x-call-link prefix="call" />.
        @endif
    </p>
</div>
