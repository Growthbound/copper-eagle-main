# Photo grid

Galleries of completed work.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `images` | array | yes | Each with `src`, `alt`, `width`, `height` |
| `columns` | int | no | `2`, `3`, or `4`. Defaults to `3` |

## Variants

| Variant | Layout |
|---|---|
| `gallery` | Responsive image grid with native-dialog enlargement |

## Notes

Every image needs descriptive `alt` text naming what the work was, not
"gallery image 3". Lazy-load everything below the fold.

`gallery` accepts `id`, optional `eyebrow`, `heading`, and `images`. Each image
requires `src`, `alt`, positive intrinsic `width`, and positive intrinsic
`height`; `caption` is optional. Malformed images are omitted. JavaScript only
adds enlargement: every valid image and caption remains visible without it.
