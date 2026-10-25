# Trust bar

Licenses, insurance, guarantees, and badges. Sits directly under the hero where
it answers "are these people legitimate?" before the visitor scrolls.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `items` | array | yes | List of `['title' => ..., 'body' => ...]` |
| `columns` | int | no | `2`, `3`, or `4`. Defaults to `3` |

## Variants

| Variant | Layout |
|---|---|
| `cards` | Three or four cards with check icons. |
| `google-rating` | The real Google average and review count, linking to the profile. |

## Notes

License and insurance numbers are business facts — read them from
`config('site.*')`, never hard-code them into a variant.
