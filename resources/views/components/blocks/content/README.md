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
| `checklist` | array | no | List of short strings rendered with check icons |
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
| `split` | Image stack beside copy, with an optional checklist. |
| `story-checklist` | Overlapping media collage beside editorial copy and a two-column proof checklist. |
| `capability-strip` | A single row of short capability names. |
| `commitment-collage` | Dark panel: copy beside a rotated photo collage. |
| `resource-cards` | Image cards with a collapsible tip. Defaults to `content/resources`. |
| `benefit-carousel` | Rotating benefit panel. Reads `content/benefits`. |

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
