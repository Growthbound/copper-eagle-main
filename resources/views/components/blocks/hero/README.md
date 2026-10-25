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
| `interior` | Deep-blue band (optionally over a darkened photo), centered uppercase eyebrow and white H1, white chevron rising into the next section. |
| `service` | Full-width primary field with service copy, actions, and optional media. |
| `location` | Service-area H1 with explicit mobile-coverage language. |
| `article` | Editorial H1 with visible author, publication date, and optional reviewer. |
| `photo` | Home page: header, service icon strip, then a full-bleed photo under a deep-blue overlay with a left-aligned H1, one copper action, and a white chevron edge. |

### `photo`

The `home` page template's hero (`/_templates/photo-hero`). Renders
`<x-site-header>`, then `service-cards/icon-strip` (unless `serviceStrip` is
false, and only when a service record has an `icon`), then the photo section.

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Section anchor. Defaults to `hero`; the H1 is `{id}-title` |
| `eyebrow` | string | no | Short uppercase kicker above the H1 |
| `heading` | string | yes | The page's only `<h1>` |
| `emphasis` | string | no | An exact phrase from `heading`, rendered weight 600. Anything else is rejected |
| `body` | string | no | One short paragraph |
| `primaryAction` | object | yes | `label` and safe `href`. The one copper `.button-orange` |
| `image` | object | yes | Governed media: `media_id`, or `src`, `alt`, `width`, `height`. Covers the section |
| `video` | object | no | Background video over the photo: `src` (a safe `.mp4` or `.webm`) and `poster` (`src`, `width`, `height`). See "Background video" below |
| `serviceStrip` | boolean | no | Defaults to `true`. `false` leaves the icon strip out |

The overlay is strongest behind the copy so white text holds contrast over any
photo. The chevron is white, so the next section should open on white.

### Background video

`hero/photo` and `hero/interior` accept an optional `video`:

```json
"video": {
    "src": "/videos/brand/hero-home.mp4",
    "poster": { "src": "/videos/brand/hero-home-poster.jpg", "width": 1280, "height": 720 }
}
```

It renders a muted, looping, `playsinline` video over the photo and under the
same overlay, `aria-hidden` because it is decorative (the poster needs no alt
text). The markup ships with `preload="none"` and no `<source>`:
`resources/js/app.js` attaches the source once the page is idle, and only when
the visitor has not asked for reduced motion, so nothing heavy loads up front
and a reduced-motion visitor sees the poster still. A small square pause
control appears in the lower corner once the video plays. The photo stays the
fallback and the priority image; on `hero/interior` the video alone also turns
on the deep-blue overlay. An unsafe source, another file type, or a poster
without its size is rejected and the video is left out.

## Notes

A hero that includes a lead form converts better on paid traffic. When adding
that variant, embed `<livewire:quote-form />` rather than rebuilding form
markup, so validation, spam protection, and attribution stay in one place.

All new variants accept `id`, optional `eyebrow`, and `heading`. Interior,
service, and location heroes accept optional `body` plus `primaryAction` and
`secondaryAction` (`label`, safe `href`). Service accepts optional governed
`image`. Interior also accepts an optional governed `image` (`media_id`, or
`src`, `alt`, `width`, `height`): it renders behind an 80% deep-blue overlay as
a decorative backdrop (`alt=""`), and without it the band is plain deep blue.
Its primary action is the copper button. It also accepts the optional `video`
described under "Background video". The chevron takes the color of a
mist, stone, or light-blue band placed directly under the hero. Location requires a validated service-area `place` and must never
imply a storefront. Article requires `author` and ISO `publishedAt`; optional
reviewer requires `name` and ISO `reviewed_at`. An article with missing
accountability metadata does not render.

Article also accepts an optional ISO `updatedAt` for a substantive revision.
Same `YYYY-MM-DD` format as `publishedAt`, and it cannot precede it — the
registry rejects an earlier date rather than rendering a page that claims it
was revised before it existed. When present it renders as a visible "Updated"
line beside the publication date and carries into the Article schema, so it is
a statement that the text genuinely changed. Leave it off for a typo fix.

Article dates render the way people read them, as the live blog does
(`Sep 8, 2023`, `Updated Oct 1, 2023`), each in a `<time datetime>` holding
the ISO date. The date comes first, then "Written by" and the author.
