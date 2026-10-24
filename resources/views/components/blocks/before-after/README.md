# Before and after

Paired before and after imagery. The highest-converting proof block for
cleaning, restoration, and exterior work.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `pairs` | array | yes | Each with `before`, `after` image arrays and a `caption` |

## Variants

| Variant | Layout |
|---|---|
| `slider` | Both sourced images without JavaScript; range-enhanced overlay when JavaScript loads |

## Notes

A side-by-side variant works without JavaScript and should be the default. If
adding a drag-to-reveal slider, it must degrade to both images visible when JS
is unavailable, and the handle needs keyboard support.

Both images in a pair must share aspect ratio or the comparison misleads.

`slider` accepts `id`, optional `eyebrow`, `heading`, `before`, and `after`.
Each image requires `src`, descriptive `alt`, intrinsic integer `width` and
`height`, `source_label`, and safe `source_url`. If either side is invalid, the
entire block renders nothing. Never pair images from different projects or
angles in a way that implies a false transformation.
