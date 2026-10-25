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
| `grid` | The services hub. When the service records carry an `icon`, four per row: the line icon above a two-line title (split before a `+`, or between the words of a two-word name), centered, no card chrome, the whole tile linking to the service page. Without icons, image cards over a tinted panel. Defaults to the site service list. |
| `icon-strip` | Mist band with diamond corners: one line icon and a two-line uppercase caption per service, copper hairline below. Rendered under the header by `hero/photo`. |

## Notes

Source services from service records, not inline arrays. The same records drive
service pages, the location page matrix, and `Service` structured data.

### `icon-strip`

Reads the service records in `content/services`. Each service with a valid
`icon` (a path under `public/`, such as `images/brand/icons/drain-cleaning.png`)
becomes one link to its service page, captioned with its `name` split onto two
lines (before a joining `+` or `&`, otherwise between the words). The icon is
decorative (`alt=""`); the caption names the link. With no service that has an
icon the block renders nothing, not an empty band.

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Anchor. Defaults to `service-strip` |
| `services` | array of strings | no | Service record slugs, in display order. Defaults to every service with an icon, in record order. A slug without a record or a valid icon is rejected |

The strip has no heading of its own: it is a `<nav aria-label="Services">`, and
on the home page it sits above the H1. Eight icons fit one row on desktop; below
900px they wrap into rows of four. Preview: `/_templates/service-icon-strip`.
