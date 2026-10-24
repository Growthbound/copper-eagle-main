# Evidence

Documented project narratives. Evidence blocks are not a license to invent
results, locations, dates, customer details, or before/after implications.

## `case-study`

Props: `id`, optional `eyebrow`, `heading`, `problem`, `work`, `outcome`,
optional `image`, and optional `facts`.

Each fact requires:

```php
[
    'label' => 'Project type',
    'value' => 'Residential',
    'source_label' => 'Approved project record',
    'source_url' => 'https://example.com/record',
]
```

The three narrative stages render only when all are present. Facts without a
safe, named source are omitted. The optional image uses the shared media
contract: safe `src`, descriptive `alt`, and positive intrinsic `width` and
`height`.

## `local-project`

Props: `id`, optional `eyebrow`, `heading`, service-area `place`, `work`,
`outcome`, `source`, and optional `image`. The source uses `label` and `href`.
The place must pass the service-area-name contract; address-like values are
rejected. Missing source, unsafe source, invalid place, or incomplete narrative
causes the block to render nothing.
