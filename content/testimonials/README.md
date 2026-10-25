# Testimonials

One JSON file per customer quote the business has permission to publish.

## Why these are separate from Google reviews

Choosing which of your own collected testimonials to publish is ordinary
marketing. Filtering someone else's public review feed to show only the good
ones is what the FTC's rule on review suppression prohibits, and it is why the
site shows Google's rating as an aggregate rather than a hand-picked selection
of Google reviews.

So: glowing quotes belong here. The Google number stays honest.

Only publish a quote the customer actually gave and agreed to have published.
An invented testimonial is a fabricated endorsement, and the rating attached to
it feeds the same claim.

## Fields

| Field | Required | Notes |
|---|---|---|
| `name` | yes | As agreed. A first name and last initial is normal |
| `quote` | yes | Their words. Trim for length, do not rewrite the meaning |
| `location` | no | The area they are in, shown beside the name |
| `rating` | no | 1–5, rendered as stars |
| `order` | no | Lower sorts first |
| `services` | no | Service slugs. Omit to show everywhere |
| `areas` | no | Area slugs. Omit to show everywhere |

## Scoping

Like questions, an unscoped testimonial shows everywhere, and adding `services`
or `areas` narrows it. A quote about gutter cleaning belongs on the gutter
cleaning pages:

```json
{
    "name": "Jordan P.",
    "location": "Springfield",
    "quote": "Our gutters had been overflowing for months. They found the clogged downspout in twenty minutes.",
    "rating": 5,
    "services": ["gutter-cleaning"],
    "order": 2
}
```

A location page showing a quote from someone in that area is far more
persuasive than a generic one, so scope by area where you genuinely have them.
