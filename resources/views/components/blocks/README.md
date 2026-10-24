# Block catalog

Every reusable section of a marketing page lives here. A page is assembled by
placing blocks one after another; no page should contain bespoke section markup.

## How to use a block

Blocks are anonymous Blade components. The folder name is the category and the
file name is the variant:

```
resources/views/components/blocks/hero/split.blade.php
```

is used in a page as:

```blade
<x-blocks.hero.split
    eyebrow="Local, licensed, and on time"
    heading="Home service you can count on."
    :cta="['label' => 'Request a visit', 'href' => '#quote']"
/>
```

The rule to remember: `blocks.<category>.<variant>`, with dashes in file names
becoming dashes in the tag (`before-after/slider.blade.php` →
`<x-blocks.before-after.slider />`).

## How to use icons

Marketing blocks use the shared outline-icon wrapper:

```blade
<x-marketing-icon name="calendar-days" class="your-layout-class" />
```

Choose the `name` from `config/marketing-icons.php`. That catalog includes a
plain-language use for every approved icon, so agents can make a deliberate
selection without guessing at filenames.

Do not paste SVG markup into a block and do not call `<x-heroicon-*>`
components directly. The wrapper locks every block to one icon family, validates
the name, applies the shared `marketing-icon` class, and keeps decorative icons
out of the accessibility tree.

## How to add a variant

1. Pick the category folder that matches the job. Do not invent a new category
   for a layout difference — a new category is for a new *kind* of section, a
   new variant is for a different arrangement of the same kind.
2. Copy an existing variant in that folder as a starting point.
3. Name the file for its layout, not for the client (`split.blade.php`,
   `centered.blade.php` — never `acme-hero.blade.php`).
4. Declare every input at the top with `@props([...])`, giving a default to
   anything optional.
5. Add the variant to the category README's table.

## Rules that keep sites on brand

- **Never hard-code a color.** Use the semantic Tailwind tokens (`bg-primary`,
  `text-on-primary`, `bg-surface`, `text-accent-on-page`) or the CSS custom
  properties they map to. Colors are owner-editable at runtime, and a literal
  hex will not follow when the palette changes.
- **Never hard-code business facts.** Phone numbers, emails, addresses, and
  service areas come from `config('site.*')`, which the admin overrides at
  runtime.
- **Always declare image dimensions.** Every `<img>` needs `width` and `height`
  matching the file's intrinsic size, plus meaningful `alt` text. A test
  enforces this on rendered pages.
- **One `<h1>` per page.** Heroes own it; every other block starts at `<h2>`.
- **Label every section** with `aria-labelledby` pointing at its heading.

## Categories

| Category | Use it for |
|---|---|
| `announcement` | Site-wide promo or notice bar, scheduled from the admin |
| `hero` | The first screen of a page, owns the `<h1>` |
| `trust-bar` | Licenses, insurance, guarantees, badges under the hero |
| `content` | General prose, prose paired with an image, and the rotating benefit panel |
| `evidence` | Sourced case studies and documented local project proof |
| `media` | Controlled video with poster, caption, and transcript |
| `service-cards` | A grid of services linking to service pages |
| `pricing` | Monthly, yearly, and toggleable pricing-card comparisons |
| `process-steps` | Numbered "how it works" sequences |
| `photo-grid` | Galleries of completed work |
| `before-after` | Paired before and after imagery |
| `testimonials` | Customer quotes and ratings |
| `team` | Staff portraits, roles, and optional professional profiles |
| `faq` | Accordion question and answer lists |
| `stats` | Numeric proof points |
| `service-areas` | Coverage lists and maps |
| `cta` | Conversion prompts and inline lead forms |
| `logo-strip` | Manufacturer, partner, or association logos |

Each folder has its own README describing the props that category expects.

Content payloads are data, never HTML. Invalid collection items are omitted;
blocks that cannot satisfy their minimum evidence or media contract render
nothing. An agent must preserve that behavior instead of inventing fallback
claims, sources, images, prices, or locations.

## Machine registry

The live `/api/content/v1/schema` response is authoritative. The registered
identifiers are:

```text
announcement/bar
content/benefit-carousel
content/capability-strip
content/commitment-collage
content/resource-cards
content/split
content/story-checklist
cta/image-split
cta/panel
cta/quote-form-split
cta/quote-form
faq/accordion
faq/highlighted
hero/split
pricing/monthly
pricing/toggle
pricing/yearly
process-steps/grid
process-steps/icon-grid
service-areas/list
service-cards/grid
team/grid
testimonials/carousel
testimonials/quotes
trust-bar/cards
trust-bar/google-rating
content/rich-text
content/direct-answer
content/inclusions-exclusions
content/comparison-table
content/table-of-contents
content/sources-reviewer
content/related-content
pricing/factors
before-after/slider
photo-grid/gallery
evidence/case-study
media/video
stats/proof
logo-strip/credentials
service-areas/grid
service-areas/coverage
evidence/local-project
cta/sticky-mobile
cta/inline-band
cta/form-confirmation
cta/visit-expectations
hero/interior
hero/service
hero/location
hero/article
```
