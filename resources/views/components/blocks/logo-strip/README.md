# Logo strip

Manufacturer, partner, and trade association logos.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Short line such as "Certified on" |
| `logos` | array | yes | Each with `src`, `alt`, `width`, `height` |

## Variants

| Variant | Layout |
|---|---|
| `credentials` | Source-required credential or membership cards |

## Notes

Only include logos the client is licensed to display. `alt` should be the
organisation name.

`credentials` accepts `id`, optional `eyebrow`, `heading`, and `items`. Every
item requires `name`, an `image` with `src`, `alt`, `width`, and `height`, plus
`source_label` and safe `source_url`. Unsupported or malformed items are
omitted. The source must prove the current credential or membership, not merely
link to the organisation homepage.
