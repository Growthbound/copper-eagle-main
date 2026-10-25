# Testimonials

Customer quotes and ratings.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `eyebrow` | string | no | Short context label above the heading |
| `items` | array | no | Explicit reviews. When omitted, permissioned records are loaded from `content/testimonials`. |
| `service` | string | no | Limits repository records to a service slug |
| `area` | string | no | Limits repository records to an area slug |
| `link` | array | no | `quotes` only. `label` and safe `href`; an outline button under the quotes, such as "Read more testimonials" |

## Variants

| Variant | Layout |
|---|---|
| `quotes` | Light-blue band with the diamond lattice: centered H2, centered quotes with their names, optional outline `link`. Defaults to the records in `content/testimonials`. Explicit `items` take `quote`, `author`, and optional `location` or `role` and `rating`. Text on the band is ink or deep blue, never white. |
| `carousel` | Livewire carousel supporting quote and video-poster cards, with three primary cards and edge peeks on desktop, one active card on mobile, and accessible dot controls. |

## Quote carousel item shape

```php
[
    'type' => 'quote',
    'title' => 'Optional review headline',
    'quote' => 'Required review text',
    'author' => 'Required customer name or approved attribution',
    'context' => 'Optional location, service, or review source',
    'avatar' => [
        'src' => '/storage/media/customer.webp',
        'alt' => 'Customer name',
        'width' => 112,
        'height' => 112,
    ],
]
```

`title`, `context`, and `avatar` are optional. Without an avatar, the component
renders the author's initials. Avatar width and height are required when an
image is supplied so the card does not shift while loading.

`type` is optional and defaults to `quote`, so existing testimonial collections
remain compatible.

## Video carousel item shape

```php
[
    'type' => 'video',
    'title' => 'Customer video title',
    'context' => 'Optional customer, source, or service label',
    'video_url' => '/media/customer-story.mp4',
    'transcript' => 'Required text transcript of the customer video.',
    'poster' => [
        'src' => '/storage/media/customer-story-poster.webp',
        'alt' => 'Customer speaking on camera',
        'width' => 1600,
        'height' => 900,
    ],
]
```

The poster remains visible until the visitor activates the play link. The
carousel does not preload or embed third-party video players. Both `video_url`
and `transcript` are required for managed content so every published video has
an accessible text equivalent.

## Publication rules

Only publish quotes the client can substantiate. An invented testimonial is a
fabricated endorsement. Synthetic reviews are allowed only inside a clearly
labelled local template preview and must never be inserted into public content
records. The same rule applies to video titles, attributions, and source labels.

Google's own rating is shown separately by `trust-bar/google-rating`, which
displays the real average and the full review count from the business's
profile. Those two are deliberately different things: see
`content/testimonials/README.md`.
