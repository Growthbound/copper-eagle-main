# Service cards

A grid of services, each linking to its own service page. The primary internal
linking surface on the homepage.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `services` | array | yes | List of service records |
| `columns` | int | no | `2` or `3`. Defaults to `3` |

Each service entry expects `title`, `body`, `href`, and an `image` array with
`src`, `alt`, `width`, and `height`.

## Variants

| Variant | Layout |
|---|---|
| `grid` | Image cards over a tinted panel. Defaults to the site service list. |

## Notes

Source services from service records, not inline arrays. The same records drive
service pages, the location page matrix, and `Service` structured data.
