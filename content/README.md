# Site content

Public content is governed by the managed content database. Every save creates
an immutable revision; Preview, Publish, Schedule, archive, restore, and
Rollback are explicit lifecycle actions shared by the owner CMS and Hermes API.

Files under `content/pages` remain the deterministic legacy/seed source. They
are not the day-to-day publishing surface. Import them with:

```bash
php artisan content:import-json --dry-run
php artisan content:import-json
```

Import creates repairable drafts and never silently replaces published content.

## The file layout is the routing table

| File | URL |
|---|---|
| `content/pages/home.json` | `/` |
| `content/pages/about.json` | `/about` |
| `content/pages/services/gutter-cleaning.json` | `/services/gutter-cleaning` |

Managed published entries take precedence at runtime. A legacy file is used
only where the application deliberately retains that fallback. Adding or
deleting a JSON file does not bypass the managed publication lifecycle.

## What a page looks like

```json
{
    "title": "Gutter cleaning in Springfield",
    "description": "One sentence for search results, under 160 characters.",
    "noindex": false,
    "blocks": [
        {
            "type": "hero/split",
            "props": {
                "heading": "Gutters cleared before the next storm.",
                "cta": { "label": "Request a visit", "href": "#quote" }
            }
        },
        {
            "type": "cta/quote-form",
            "props": { "heading": "Tell us about your home." }
        }
    ]
}
```

| Field | Required | Notes |
|---|---|---|
| `title` | yes | Used as the `<title>`, with the site name appended |
| `description` | no, but expected | The meta description. Omitting it makes search engines invent one |
| `noindex` | no | `true` keeps the page out of search results and the sitemap |
| `blocks` | yes | Sections rendered in order. May be empty, but the page will look it |

## Blocks

`type` is `category/variant`, matching a file under
`resources/views/components/blocks`. So `"hero/split"` renders
`resources/views/components/blocks/hero/split.blade.php`.

`props` are that block's inputs. Every block declares what it accepts at the
top of its file in `@props([...])`, and each category folder has a README
describing its fields. The local template gallery at `/templates` renders every
block with sample props — copying a working block is more reliable than
composing one from the documentation.

Some blocks read shared business data when a prop is omitted. `service-cards`
falls back to the service list in `config/site.php`, so the cards, the quote
form's dropdown, and future service pages cannot drift apart. Pass the prop
only when a page needs a deliberately different selection.

## Check your work

```bash
php artisan content:check
```

This reads every page file and reports, by file name:

- JSON that does not parse, and where
- a missing `title` or `blocks`
- a block `type` with no matching template
- titles and descriptions long enough to be truncated in search results
- pages with no description, or no blocks

A failure here is a page that would not render. Warnings are worth fixing but
will still deploy. Managed drafts are also validated by the CMS and
`/api/content/v1/schema` contract before preview or publication.

## Governed page templates

- `standard` is the flexible registered-block page.
- `service-pillar` is the complete service decision and conversion page.
- `location-pillar` is the locally relevant service-area page.
- `decision-guide` is the evidence-led editorial answer page.

Fixed templates own their section order and block types. An editor or agent may
change structured props, but cannot swap a required section to arbitrary code.

## Hermes

Hermes must read `/api/content/v1/schema` before composing content. Writes use
scoped Basic-auth keys, `Idempotency-Key`, `If-Match` for existing entries, and
`X-Request-ID`. See `docs/content/hermes-content-contract.md`.

## Rules worth knowing

- **Never put a phone number, email, or address in a page file.** Those come
  from `config/site.php` and are edited in the admin, so they can change
  without a deploy. A hard-coded number goes stale silently.
- **Never put a colour in a page file.** Blocks use the site palette, which the
  owner controls under Admin → Theme.
- **Images need real dimensions.** Every image prop takes `width` and `height`
  matching the file, plus `alt` text. A test enforces this on rendered pages.
- **One `<h1>` per page**, and heroes own it. Every other block starts at
  `<h2>`, so a page should have exactly one hero.
- **Never edit an old revision.** Save a new draft, preview it, and publish the
  reviewed revision. Rollback also creates a new immutable revision.
