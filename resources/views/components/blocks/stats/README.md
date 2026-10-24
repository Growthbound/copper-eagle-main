# Stats

Numeric proof points: years in business, homes served, response time.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `items` | array | yes | Each with `value` and `label` |

## Variants

| Variant | Layout |
|---|---|
| `proof` | Source-required statistic cards |

## Notes

Keep `value` a string so units and symbols survive (`'15+'`, `'< 1 hr'`).
Only use numbers the client can defend.

`proof` accepts `id`, optional `eyebrow`, `heading`, and `items`. Every item
requires string `value`, `label`, `source_label`, and a safe `source_url`;
`context` is optional. A number without a valid citation is omitted. Never
convert an estimate, review-platform count, or marketing aspiration into a
proof statistic.
