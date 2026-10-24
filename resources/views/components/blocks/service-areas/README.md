# Service areas

Coverage lists and maps. Important for local ranking and for cutting calls from
outside the service radius.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `areas` | array | yes | List of `['label' => ..., 'href' => ...]` |
| `map` | array | no | `['embed' => ..., 'title' => ...]` for a map variant |

## Variants

| Variant | Layout |
|---|---|
| `list` | Linked grid of places or services. |
| `grid` | Card grid of governed service-area names and links. |
| `coverage` | Full-width mobile-service illustration plus crawlable area links. |

## Notes

Area names should link to their location pages once those exist — this block is
the main internal link path into the service-by-city matrix.

A map embed is a third-party iframe: load it lazily, give it a `title`, and
keep the text list present so the areas are readable without the iframe.

`grid` and `coverage` use `areas` shaped as:

```php
['name' => 'North Valley', 'href' => '/areas/north-valley', 'note' => 'Optional']
```

The name validator rejects digits and common street-address tokens. Links must
use the shared safe-link contract. `coverage` accepts optional `body` and a
descriptive `coverageLabel`; its visual is a mobile-service illustration, not a
storefront pin or precise geographic claim. Never add an address, directions,
coordinates, or office language unless the business operates a verified public
location.
