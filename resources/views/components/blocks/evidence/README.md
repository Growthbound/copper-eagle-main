# Evidence

Documented project narratives. Evidence blocks are not a license to invent
results, locations, dates, customer details, or before/after implications.

## `case-study`

Props: `id`, optional `eyebrow`, `heading`, `problem`, `work`, `outcome`,
optional `image`, and optional `facts`.

Each fact requires:

```php
[
    'label' => 'Project type',
    'value' => 'Residential',
    'source_label' => 'Approved project record',
    'source_url' => 'https://example.com/record',
]
```

The three narrative stages render only when all are present. Facts without a
safe, named source are omitted. The optional image uses the shared media
contract: safe `src`, descriptive `alt`, and positive intrinsic `width` and
`height`.

## `local-project`

Props: `id`, optional `eyebrow`, `heading`, service-area `place`, `work`,
`outcome`, `source`, and optional `image`. The source uses `label` and `href`.
The place must pass the service-area-name contract; address-like values are
rejected. Missing source, unsafe source, invalid place, or incomplete narrative
causes the block to render nothing.

## `check-ins`

The job check-ins the team posts, shown on the home page as "Local Reviews",
as on the live site. They are the team's own write-ups, not customer reviews:
never put a customer's review or a rating here, and nothing here feeds review
markup.

Props: `id` (defaults to `check-ins`), optional `eyebrow`, `heading`, and
`items`: up to 60 check-ins, newest first. Each check-in needs:

```php
[
    'author' => 'Jordan Walker',       // who did the work
    'date' => '2026-09-01',            // YYYY-MM-DD, shown as "September 1, 2026"
    'body' => 'What we found and did.', // optional; a blank line starts a new paragraph
    'photos' => [                      // optional, up to eight
        ['src' => '/images/brand/check-ins/…jpg', 'alt' => 'What the photo shows', 'width' => 1200, 'height' => 900],
    ],
]
```

A check-in needs a write-up, photos, or both. Each renders as a card: square
copper initials, the name and date, the write-up in italics, and the photos in
a sideways strip with a square button per photo. Cards alternate white and
mist. Two show at first; "Load more check-ins" reveals two more at a time.
Without JavaScript every card shows and the strip scrolls by touch.

Job photos must not identify a customer: no house fronts, house numbers,
faces of customers, or camera location data. Strip metadata before adding a
photo.
