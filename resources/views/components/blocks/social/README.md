# Social

Invitations to follow the business on its social profiles. Both variants are
static: no third-party embed, script, or tracking. Images are served from this
site and every outbound link opens in a new tab with `rel="noopener"` and a
visually hidden "(opens in a new tab)" note.

## Variants

| Variant | Layout |
|---|---|
| `feed` | Heading, optional body, and profile handles (uppercase network label over the handle) on the start side; a grid of square post images on the end side, five per row on desktop, three on tablets, two on phones. Each image links to its post. |
| `profiles` | Centered heading, then one image tile per profile in a wrapping row, beside an optional contact card: an image (such as a vCard QR code) with a short label under it, linking to a file such as `/s/contact.vcf`. |

## `feed` props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Section anchor. Defaults to `social`; the H2 is `{id}-title` |
| `eyebrow` | string | no | Short uppercase kicker |
| `heading` | string | yes | Renders as the `<h2>` |
| `body` | string | no | One short line |
| `profiles` | array | no | `label` (the network, e.g. `INSTAGRAM`), `handle` (e.g. `@company`), optional absolute http(s) `href`. Without a valid `href` the handle is plain text |
| `posts` | array | no | Up to 12, in display order. Each needs an absolute http(s) `href` to the post and an `image` (`src`, `alt` describing the photo, `width`, `height`). Invalid posts are omitted |

## `profiles` props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Section anchor. Defaults to `social-profiles` |
| `eyebrow` | string | no | Short uppercase kicker |
| `heading` | string | yes | Renders as the `<h2>` |
| `body` | string | no | One short line |
| `profiles` | array | yes | Each needs an absolute http(s) `href` and an `image` whose `alt` names the profile ("Copper Eagle Plumbing on Instagram"): the alt text is the link's name |
| `card` | object | no | `label`, safe `href` (relative paths allowed, e.g. `/s/JW.vcf`), and `image` with alt text |

The block renders nothing when no tile is valid.

## Rules

- Profile and post addresses are absolute `http`/`https` links on another site;
  relative paths, `javascript:` and other schemes are rejected.
- Post images are the business's own posts, downloaded with approval. Write alt
  text that describes the photo, not the caption.
- Phone numbers and emails never go in these props; they come from site
  settings (`config('site.*')`).
