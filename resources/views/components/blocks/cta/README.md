# Call to action

Conversion prompts, including inline lead forms.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `eyebrow` | string | no | Short kicker above the heading |
| `heading` | string | yes | Renders as `<h2>` |
| `body` | string | no | One supporting sentence |
| `cta` | array | no | `['label' => ..., 'href' => ...]` |
| `form` | bool | no | Embed the lead form instead of a button |
| `image` | array | split variants only | `src`, `alt`, `width`, and `height`; omitted when incomplete |
| `image.fit` | string | no | Image-split only; `cover` (default) or `contain` for transparent cutouts |
| `submitLabel` | string | no | Split-form button label; defaults to `Request a visit` |
| `showMessage` | bool | no | Split form only; defaults to `false` for the compact layout |

## Variants

| Variant | Layout |
|---|---|
| `quote-form` | Dark panel with copy beside the lead form. |
| `quote-form-split` | The form on a mist card beside a photo, on white. Square inputs, deep-blue uppercase labels, copper submit. Errors are tied to their fields with `aria-describedby`. |
| `panel` | Dark full-width band: optional small `logo` (governed media whose alt names it, such as the Google mark), centered eyebrow, H2, optional `body` (plain text; a blank line starts a new paragraph), and an optional `cta` button with an "or call" line. Without `cta` the band is a statement, like the live guarantee band, with no button and no call line. `tone`: `primary` (default, deep blue with a copper button, optionally over a darkened decorative `image`) or `copper` (the solid copper guarantee band with the lattice and a deep-blue button; ignores `image`). |
| `image-split` | Full-width primary CTA with inner-margin copy and prominent right-side media. |
| `sticky-mobile` | Safe-area-aware single action visible only at mobile widths. |
| `inline-band` | Centered prompt: large deep-blue H2, optional body, one button, and quieter secondary and call links. `emphasis` (an exact phrase from the heading) is underlined in copper. `background`: `white` (default) or `mist`. `buttonStyle`: `copper` (default) or `sky`. Optional governed `image` (such as a portrait) on the start side with the prompt beside it; phones stack it above. It comes last in the markup so the heading is read first. |
| `form-confirmation` | Ordered post-submit expectation sequence. |
| `visit-expectations` | Icon-governed service-visit expectation cards. |

## Notes

Use the shared `<x-forms.website>` renderer for both form variants. The server
selects native or hosted HighLevel intake; `purpose` is `inquiry` (default),
`feedback`, or `comments`. Missing hosted configuration displays a contact
alternative. Native source is retained for the eventual migration away from
HighLevel; real native submission is disabled while HighLevel is selected.

Use `<x-blocks.cta.quote-form-split>` when a screenshot or page composition
calls for the conversion form and image to share one full-width panel. It uses
the same provider renderer; `submitLabel` and `showMessage` customize native/demo
forms only. HighLevel owns its fields and confirmation.

`showContact` (boolean, default `false`) adds the phone number as a copper call
button under the heading and the email address as a `mailto:` link under the
body. Both come from site settings (`config('site.phone')`, `config('site.email')`)
and each is hidden when blank; never type them into content. An optional
governed `secondaryImage` stacks under the first photo.

The local, noindex reference is available at `/_templates/contact-form`.

Use `<x-blocks.cta.image-split>` for a large promotional CTA where the
background must span the viewport but the copy must follow the shared inner
margin. On mobile, the copy and button remain first and the media stacks below.
Use `image.fit="contain"` for a transparent worker cutout; the default `cover`
works with standard photography.

Phone links must come from `config('site.phone')` so call tracking numbers can
be swapped without touching templates.

The new conversion variants use `primaryAction` and optional `secondaryAction`
with `label` and safe `href`. An invalid primary action causes `sticky-mobile`
and `inline-band` to render nothing; an invalid secondary action is omitted.
`form-confirmation.steps` is an array of non-empty strings.
`visit-expectations.items` contains `label`, `description`, and an optional icon
name from `config/marketing-icons.php`; unknown icons fall back to the shared
information symbol. These blocks never invent response times, availability, or
service guarantees.
