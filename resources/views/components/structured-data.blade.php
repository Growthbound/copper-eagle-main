{{--
    Emits the page's JSON-LD graph. The business node is always included; pages
    pass whatever else describes them.
--}}
@props(['nodes' => []])

<script type="application/ld+json">
{!! json_encode(
    App\Support\StructuredData::graph($nodes),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
) !!}
</script>
