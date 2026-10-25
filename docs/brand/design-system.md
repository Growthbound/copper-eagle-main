# Copper Eagle design system

**Binding on every visual change.** Read this before touching a block, page
template, stylesheet, image, or layout. It is the visual counterpart of
`docs/brand/voice.md`: the voice governs every word a customer reads, this
governs everything a customer sees.

The reference is the business's existing site, coppereagleplumbing.com, rebuilt
on this platform's governed blocks. The local template gallery at `/templates`
renders every block and every page template with fictional content; it is the
visual source of truth for how each piece should look. If the gallery and this
file disagree, stop and reconcile them in the same change.

Tests in `tests/Unit/DesignSystemContractTest.php` and
`tests/Feature/CopperEaglePageTemplatesTest.php` hold the code to this file.
A change that makes them fail is a design change, and needs this file updated
in the same commit, with the reason.

## The rules that prevent drift

1. **Tokens only.** Colors, fonts, corner radii, and the copper gradient live in
   `resources/css/theme.css` (and the editable colors in `config/theme.php`).
   Blocks and `app.css` use the semantic tokens below. No hex values, no
   `rgb()`, no Tailwind palette classes (`text-white`, `bg-slate-*`), and no
   literal `border-radius` values outside the token definitions. Brand styles
   live in `resources/css/brand/` (`chrome.css` for header and footer,
   `sections.css` for shared blocks, `blocks.css` for Copper Eagle blocks), are
   unlayered, and load last. Background images there use paths relative to the
   stylesheet, e.g. `url('../../../public/images/brand/patterns/diamond-lattice.jpg')`,
   which work in development and in the production build.
2. **One font.** Outfit (self-hosted through `@fontsource/outfit`) for headings
   and body. No other font family anywhere.
3. **Pages are templates.** Every public page uses one of the fixed page
   templates below. Content agents choose a template and fill its structured
   props through the CMS or the content API. They never add sections, choose
   block types inside a fixed template, or pass CSS classes.
4. **New look means a code change.** A new section type, variant, color, or
   layout is a pull request that updates the block, its README, this file, the
   gallery preview, and the tests together. Never approximate it with existing
   props.
5. **Real assets only on real pages.** Brand files in `public/images/brand` are
   the business's own. Staff photos appear only beside the person's confirmed
   name on a real page, never in a fictional template preview.

## Color

| Token | Hex | Role | Text that may sit on it |
|---|---|---|---|
| `--color-teal` (Primary) | `#284758` | Deep blue. Dark sections, footer, interior heroes, outline buttons, display headings | white, `--color-orange-light` |
| `--color-orange` (Accent) | `#a56542` | Copper. Flat button fill, hairlines, icon accents. The midpoint of the copper gradient | white |
| `--color-orange-ink` | `#9a5a38` | Copper text on white or mist (emphasized words in headings) | — |
| `--color-orange-light` | `#e8b591` | Copper text on deep blue | — |
| `--color-mist` (Surface) | `#edf2f9` | Pale blue-gray. Service icon strip, card fields, alternate sections | ink, teal |
| `--color-ink` | `#42474a` | Charcoal slate. Body text and most headings | — |
| `--color-muted` | `#5c6367` | Secondary text, captions | — |
| `--color-sky` | `#a3c3d2` | Light blue. Announcement bar, testimonial band, light buttons | ink, teal (never white) |
| `--color-stone` | `#f2eeea` | Warm stone. Behind feature cards on service and about pages | ink, teal, orange-ink |
| `--color-copper-light` | `#c4845a` | Copper gradient start | — |
| `--color-copper-dark` | `#864629` | Copper gradient end. Also the solid "guarantee" band | white |
| `--color-white` | `#ffffff` | Page background | ink, teal |

The copper gradient is `--theme-accent-gradient`: 40 degrees from
`--color-copper-light` to `--color-copper-dark`. Primary buttons, the service
area band, and the footer call button use it.

### Deliberate departures from the live site

The live site puts white text on its lightest copper (3.1:1) and on its light
blue (1.9:1), which fails WCAG AA. Here:

- copper is slightly deeper (`#c4845a` → `#864629`), so white button text
  measures 4.6:1 at the button's center;
- light-blue surfaces carry ink or deep-blue text, never white.

Every pair in the table above passes AA for normal text. `ColorContrastTest`
enforces the editable pairs.

## Typography

- **Family:** Outfit, the closest free match to the live site's Arboria
  (geometric, single-storey `a`, round `o`). If an Adobe Fonts license for
  Arboria is obtained later, swap `--font-heading` and `--font-body` in
  `resources/css/theme.css`; nothing else changes.
- **Weight:** headings are weight 400. Emphasis inside a heading is weight 600
  or copper (`--color-orange-ink`), never both at once, and never more than
  one phrase per heading.
- **Scale:**

| Element | Size | Line height | Notes |
|---|---|---|---|
| H1 | `clamp(2.5rem, 4.6vw, 3.5rem)` | 1.08 | One per page. White on photo or deep blue heroes |
| H2 | `clamp(1.9rem, 3.2vw, 2.5rem)` | 1.15 | The side-layout feature cards heading uses the H1 size, as on the live site |
| H3 | `clamp(1.25rem, 1.8vw, 1.6rem)` | 1.25 | |
| Body | `1rem` | 1.85 | `--color-ink`, max ~65 characters per line |
| Label (eyebrow, icon caption, button) | `0.78rem`–`0.85rem` | — | Uppercase, weight 500, letter-spacing `0.16em` |

## Shape

- **Square corners.** Buttons, cards, inputs, panels, and images are
  rectangular: `--radius-control`, `--radius-card`, `--radius-panel`, and
  `--radius-feature` are all `0`.
- **Circles** only where the live site uses them: portrait and feature photos
  (`content/split` portraits, `content/feature-cards`), carousel arrows, and
  social icons. `--radius-pill` is the only rounded token.
- **Angles, not curves.** Hero bottom edges are chevrons rising into the next
  section: the home photo hero's carries a thin copper line, interior heroes'
  do not. Each hero block draws its own edge in the next section's color, so
  pages never add dividers by hand. The service area band is an elongated
  hexagon. The footer has a centered notch holding the stacked logo.
- **Patterns:** the diamond lattice (`patterns/diamond-lattice.jpg`) behind
  testimonial bands, the eagle watermark (`patterns/eagle-watermark.png`)
  behind feature cards, and the diamond corners (`patterns/diamond-corners.png`)
  behind the service icon strip. No other textures.

## Components

### Buttons

| Class | Look | Use |
|---|---|---|
| `.button.button-orange` | Copper gradient, white uppercase label | The one primary action per section |
| `.button.button-outline` | Transparent, 2px deep-blue border, deep-blue label | Secondary action on light backgrounds |
| `.button.button-sky` | Light blue fill, deep-blue label | Soft prompt on white ("Reach out to us today") |
| `.button.button-teal` | Deep blue fill, white label | Primary action on light-blue or copper backgrounds |

Labels are uppercase and short. Calls always render through `<x-call-link>`, so
the number comes from site settings and the `tel:` link always matches the
displayed number.

### Site chrome

- **Announcement bar:** light blue, centered uppercase label with an arrow,
  deep-blue text. Managed in the admin under Announcement.
- **Header:** white bar. Navigation left, horizontal logo centered, copper
  "Call or text" button right. Collapses to logo plus menu on small screens.
- **Service icon strip:** under the header on the home page only. Mist
  background with diamond corners, the eight line icons with uppercase
  captions, copper hairline below. Driven by service records with an `icon`.
- **Footer:** deep blue. Centered notch with the stacked logo, the copper call
  button, and the emergency note; services on the left; company links and
  social links on the right.

### Section backgrounds

White, mist, stone, sky with the diamond lattice, deep blue (optionally over
a darkened photo), solid copper (`--theme-copper-band`, the guarantee band,
with the lattice), or the copper hexagon band. Alternate them; never place two
dark sections back to back.

Interior heroes are deep blue over a photo, with a centered uppercase eyebrow,
a centered white H1, and a white chevron rising into the next section.

### Cards, points, and paragraphs

- `content/feature-cards` takes two to six cards. Up to four sit in one row;
  five or six sit in rows of three. Below 900px four or more become rows of
  two, and phones show one card per row.
- A `content/split` checklist point is a short string, or a `{label, text}`
  pair: the label sits on its own line in weight 600, the text under it, both
  in the body color, beside the copper check.
- The `content/split` body, feature-card bodies, the `cta/panel` body, and
  `faq/accordion` answers are plain text. A blank line starts a new paragraph;
  they never carry HTML.

## Brand assets

All in `public/images/brand`, downloaded from the live site with the owner's
approval:

| File | Use |
|---|---|
| `logo-horizontal.png` (1200×245) | Header. Charcoal wordmark with copper mark |
| `logo-stacked.png` (936×1012) | Footer notch, social cards |
| `icons/*.png` (320×320) | One per service, matched by service record `icon` |
| `patterns/diamond-lattice.jpg`, `patterns/eagle-watermark.png`, `patterns/diamond-corners.png`, `patterns/footer-notch.png` | Backgrounds listed under Shape |
| `photos/*.jpg` | Real job and staff photos. Staff photos only beside the person's confirmed name |
| `photos/master-plumber-badge.png` | The license badge. A claim: publish only where the owner has confirmed it |

Never recolor, stretch, or crop the logo. Keep clear space of at least the
height of the copper mark around it.

## Page templates

Every public page uses one of these fixed templates. The section order is
fixed by `app/Content/PageTemplateRegistry.php`; content changes props only.

| Template | Page | Sections, in order |
|---|---|---|
| `home` | Home | `hero/photo` (with the service icon strip) · `content/split` · `testimonials/quotes` · `content/feature-cards` · `service-areas/list` · `cta/inline-band` |
| `services-hub` | Services | `hero/interior` · `service-cards/grid` · `cta/inline-band` · `content/split` |
| `service-detail` | One service | `hero/interior` · `content/split` · `content/feature-cards` · `content/split` · `cta/panel` · `faq/accordion` · `cta/inline-band` |
| `emergency` | 24/7 Emergency | `hero/interior` · `content/split` · `pricing/factors` · `cta/inline-band` |
| `about` | About | `hero/interior` · `content/split` · `content/feature-cards` · `cta/inline-band` |
| `team` | Team | `hero/interior` · `team/grid` · `cta/inline-band` |
| `reviews` | Reviews | `hero/interior` · `testimonials/quotes` · `cta/panel` · `cta/inline-band` |
| `contact` | Contact | `hero/interior` · `cta/quote-form-split` · `service-areas/list` · `cta/panel` |

Blog posts use `standard` with `hero/article` followed by `content/rich-text`;
`decision-guide` is for long, evidence-led guides. `standard` also serves the
other one-off pages (legal pages, the social media page, thank-you pages) and
inherits the same tokens. The plumbing FAQ page is `standard` with
`hero/interior` · `faq/accordion` · `cta/inline-band`; each answer leads with the
answer and restates only what the company's own pages say.

The contact page's quote form asks for first and last name as separate required
fields, requires a phone number (the company calls or texts back) with email
marked optional, and shows text-message consent as a `Text Message Consent (optional)`
fieldset of separate, unticked `.quote-consent` boxes, with the Privacy Policy
and SMS Terms links in the note beneath them. The consent wording is the
company's registered text and is never restyled into marketing copy.

Pages keep the live site's addresses (e.g. `/24-7-emergency-plumber-salt-lake-utah`).
Generated service and area pages are folded into the real pages through
`content/registries/url-intent-ledger.json`, so record links always point at
the page that owns the topic.

## Checklist for any visual change

- [ ] Uses only tokens from `resources/css/theme.css`.
- [ ] Square corners, except the circles listed under Shape.
- [ ] Outfit only; headings weight 400; labels uppercase with `0.16em` tracking.
- [ ] Every text and background pair is in the color table.
- [ ] The gallery preview at `/templates` is updated and checked at 1440px,
      768px, and 390px.
- [ ] This file, the block README, and the tests changed in the same commit.
