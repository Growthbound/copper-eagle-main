{{--
    Tags that belong at the end of the document: chat widgets, call tracking,
    and anything else that should not compete with the first paint.
--}}
@php($integrations = App\Support\Integrations::forPublicPage())

@if (isset($integrations['gtm_container_id']))
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id={{ $integrations['gtm_container_id'] }}"
            height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe>
    </noscript>
@endif

@if (isset($integrations['ctm_account_id']))
    <script async src="https://{{ $integrations['ctm_account_id'] }}.tctm.co/t.js"></script>
@endif

@if (isset($integrations['body_snippet']))
    {!! $integrations['body_snippet'] !!}
@endif
