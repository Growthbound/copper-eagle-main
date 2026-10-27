{{--
    Emits the page's JSON-LD, one script block per node. The business node is
    always included; pages pass whatever else describes them.
--}}
@props(['nodes' => []])

@foreach (App\Support\StructuredData::documents($nodes) as $document)
<script type="application/ld+json">
{!! json_encode(
    $document,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
) !!}
</script>
@endforeach
