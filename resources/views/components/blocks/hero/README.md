# Hero

The first screen of a page. Owns the page's single `<h1>`. Every page should
open with exactly one hero.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `eyebrow` | string | no | Short kicker above the heading |
| `heading` | string | yes | Renders as the `<h1>` |
| `body` | string | no | One or two sentences |
| `cta` | array | no | `['label' => ..., 'href' => ...]` |
| `secondary` | array | no | Same shape as `cta`, rendered as a text link |
| `image` | array | no | `['src' => ..., 'alt' => ..., 'width' => ..., 'height' => ...]` |

## Variants

| Variant | Layout |
|---|---|
| `split` | Header, copy on the left, tall image on the right. Owns the page `<h1>`. |
| `interior` | Centered H1 for standard interior pages. |
| `service` | Full-width primary field with service copy, actions, and optional media. |
| `location` | Service-area H1 with explicit mobile-coverage language. |
| `article` | Editorial H1 with visible author, publication date, and optional reviewer. |

## Notes

A hero that includes a lead form converts better on paid traffic. When adding
that variant, embed `<livewire:quote-form />` rather than rebuilding form
markup, so validation, spam protection, and attribution stay in one place.

All new variants accept `id`, optional `eyebrow`, and `heading`. Interior,
service, and location heroes accept optional `body` plus `primaryAction` and
`secondaryAction` (`label`, safe `href`). Service accepts optional governed
`image`. Location requires a validated service-area `place` and must never
imply a storefront. Article requires `author` and ISO `publishedAt`; optional
reviewer requires `name` and ISO `reviewed_at`. An article with missing
accountability metadata does not render.

Article also accepts an optional ISO `updatedAt` for a substantive revision.
Same `YYYY-MM-DD` format as `publishedAt`, and it cannot precede it — the
registry rejects an earlier date rather than rendering a page that claims it
was revised before it existed. When present it renders as a visible "Updated"
line beside the publication date and carries into the Article schema, so it is
a statement that the text genuinely changed. Leave it off for a typo fix.
