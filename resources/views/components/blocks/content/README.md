# Content

General prose sections, optionally paired with imagery. The fallback when no
more specific block fits.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `eyebrow` | string | no | Short kicker above the heading |
| `heading` | string | yes | Renders as `<h2>` |
| `body` | string\|slot | yes | Paragraph text, or use the default slot for rich content |
| `image` | array | no | `['src' => ..., 'alt' => ..., 'width' => ..., 'height' => ...]` |
| `inset` | array | no | Optional overlapping image using the same metadata contract |
| `align` | string | no | `left` or `right`, which side the image sits on |
| `checklist` | array | no | Short strings rendered with check icons; `split` also takes `{label, text}` pairs |
| `items` | array | no | Story checklist proof points rendered in a responsive grid |

## Variants

| Variant | Layout |
|---|---|
| `rich-text` | Structured long-form nodes with controlled headings, text marks, lists, media, callouts, quotes, and dividers. |
| `direct-answer` | Concise answer card with optional supporting copy, cited source, and contextual next step. |
| `inclusions-exclusions` | Paired lists that state service scope and boundaries. |
| `comparison-table` | Semantic, horizontally scrollable criteria table. |
| `table-of-contents` | Current-page anchor list; non-anchor URLs are rejected. |
| `sources-reviewer` | Dated sources and optional accountable reviewer. |
| `related-content` | Safe internal or external links to relevant next reading. |
| `split` | Square photo or circle portrait beside copy, or a statement heading beside the detail copy when there is no image. See below. |
| `story-checklist` | Overlapping media collage beside editorial copy and a two-column proof checklist. |
| `capability-strip` | A single row of short capability names. |
| `commitment-collage` | Dark panel: copy beside a rotated photo collage. |
| `resource-cards` | Image cards with a collapsible tip. Defaults to `content/resources`. |
| `benefit-carousel` | Rotating benefit panel. Reads `content/benefits`. |
| `feature-cards` | Two to six round photo cards with H3 titles, beside a large H2 (`side`) or under a centred one (`stacked`). |

## Notes

If you find yourself passing raw HTML into `body`, the page probably wants a
more specific block instead.

### `rich-text`

Pass a section-owned `heading` (rendered as H2), optional `eyebrow`, and a
`nodes` array. Nodes are normalized before rendering. Supported node types are
`heading` (levels 3–6 only), `paragraph`, `unordered-list`, `ordered-list`,
`image`, `callout`, `quote`, and `divider`. Text segments support `strong`,
`emphasis`, `code`, and safe links. Images require alt text plus positive
intrinsic width and height. Do not pass HTML strings.

### `direct-answer`

Pass `heading` and a structured `answer` segment array. Optional props are
`eyebrow`, `supporting`, `source`, and `nextStep`. Source and next-step links
must use an allowed URL form. The content validator warns when an answer falls
outside the 40–70 word editorial target; it never pads or rewrites supplied
copy. Empty answers render nothing.

The `story-checklist` variant accepts `image` and `inset` arrays. Each image
must include non-empty `src` and `alt` values plus positive `width` and
`height` values. An invalid main image produces the copy-only layout; an
invalid inset leaves the valid main image in place.

Use the local, noindex reference at `/_templates/story-checklist` when choosing
or adapting this composition.

Use `/_templates/rich-content` and `/_templates/direct-answer` as the canonical
local specimens. Both routes are catalog allow-listed and unavailable in
production.

The remaining editorial variants use the shared `id`, `eyebrow`, and `heading`
props:

- `inclusions-exclusions`: `included` and `excluded` are arrays of non-empty
  strings. Empty or non-string entries are omitted.
- `comparison-table`: `caption`, `columns` (`key`, `label`), and `rows`
  (`label`, `values` keyed by column). Column keys must be lowercase identifiers.
- `table-of-contents`: `items` contain `label` and a lowercase `#anchor`; links
  to other pages are rejected.
- `sources-reviewer`: each source requires `label`, safe `url`, and ISO
  `reviewed_at`; optional `publisher`. Reviewer requires `name`, `role`, and ISO
  `reviewed_at`.
- `related-content`: each item requires `title` and safe `href`; `type` and
  `summary` are optional.

Never manufacture sources, review dates, or comparison values to fill a card.
See the matching `/_templates/*` previews for fictional payload examples.

### `split`

The two-column workhorse. With an `image`, the photo sits beside the copy
(eyebrow, H2, optional lead, body, checklist, copper button). With no image,
the H2 and button form a large right-aligned statement on one side and the
eyebrow (shown as a copper subheading), lead, body, and checklist sit on the
other. Every new prop is optional and falls back to the original look.

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Anchor; the H2 is `{id}-title` (`split-title` without one) |
| `eyebrow` | string | no | Uppercase label above the H2; a copper subheading in the no-image layout |
| `heading` | string | yes | The H2 |
| `emphasis` | string | no | An exact phrase from `heading`, rendered in weight 600. Anything else is rejected |
| `lead` | string | no | A bold opening sentence before the body |
| `body` | string | yes | Plain text. A blank line starts a new paragraph |
| `checklist` | array | no | Points with copper checks. Each is a short string, or a `{label, text}` record whose label renders in weight 600 on its own line above the text. Other shapes are rejected |
| `cta` | array | no | `label` and safe `href`; the copper button |
| `image` | array | no | Governed media. Cropped square |
| `imageShape` | string | no | `rectangle` (default) or `circle` (the portrait) |
| `imageSide` | string | no | `start` (default, photo first) or `end` (copy first) |
| `inset` | array | no | Governed media. Over a circle it is the badge on the lower-left edge (shown whole, transparent PNGs welcome); beside a square it overlaps the lower-right corner |
| `stamp` | string | no | Short round copper label over the photo |
| `background` | string | no | `white` (default), `mist`, or `stone`: the full-width band behind the section |

Stacks to one column below 900px: photo first for `imageSide="start"`, copy
first for `end`.

`body` is plain, escaped text: a blank line (`\n\n`) starts a new `<p>`, and a
single line break stays inside its paragraph. A checklist point given as
`{"label": "Expert Technicians", "text": "Our highly trained…"}` renders the
label in bold above its text; a string renders as before.

### `feature-cards`

Round photo cards (`--radius-pill`), each with an H3 title and a short body.
Used by the `home` (`assurances`), `service-detail` (`offerings`), and `about`
(`values`) page templates. Preview: `/_templates/feature-cards`.

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Anchor. Defaults to `features`; the H2 is `{id}-title` |
| `eyebrow` | string | no | Short uppercase kicker above the H2 |
| `heading` | string | yes | The H2 |
| `emphasis` | string | no | An exact phrase from `heading`, rendered in the copper accent. Anything else is rejected |
| `body` | string | no | One short paragraph under the H2 |
| `items` | array | yes | Two to six `{title, body, image}` records; all three are required and `image` is governed media (`media_id`, or `src`, `alt`, `width`, `height`). A blank line in `body` starts a new paragraph |
| `layout` | string | no | `side` (default): large H2 on the left, cards to the right. `stacked`: centred deep-blue H2 above, copper card titles |
| `background` | string | no | `white` (default) or `stone` |
| `watermark` | boolean | no | Defaults to `true`. The eagle watermark behind a `side` heading; ignored for `stacked` |

Photos of any aspect ratio are cropped to a circle. A card missing its title,
body, or a valid image is left out, and with fewer than two cards left the
section renders nothing, and cards after the sixth are never shown. Up to four
cards sit in one row on desktop and five or six sit in rows of three; four or
more become rows of two below 900px, and every layout is a single column on
phones.
