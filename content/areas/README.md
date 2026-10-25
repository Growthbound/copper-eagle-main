# Service areas

One JSON file per place the business serves. The filename is the slug, so
`springfield.json` is served at `/areas/springfield`.

Each area also produces a page for every service: `springfield` plus
`gutter-cleaning` gives `/services/gutter-cleaning/springfield`. Six services and
ten areas is sixty pages from sixteen files.

These are **not** editable from the admin. Areas generate pages, so they belong
to whoever maintains the repository.

## Fields

| Field | Required | Notes |
|---|---|---|
| `name` | yes | The place name on its own, without the state |
| `region` | no | State or county. Shown as "Springfield, Illinois" |
| `summary` | strongly recommended | Something true about *this* place |
| `landmarks` | no | Neighbourhoods or districts, rendered as a strip |
| `order` | no | Lower sorts first |

## Write something genuinely local

`content:check` warns about an area with no `summary` and no `landmarks`,
because sixty pages that differ only by a swapped place name are doorway pages
and search engines penalise them.

A good summary says something that is only true here — the housing stock, the
age of the builds, what tends to go wrong with homes in that area:

```json
{
    "name": "Springfield",
    "region": "Illinois",
    "summary": "Many of Springfield's older homes still have their original gutters, so we often find loose hangers and seams that need resealing.",
    "landmarks": ["Downtown", "Westchester", "Leland Grove"],
    "order": 1
}
```

If there is nothing true to say about a place yet, leave the record out rather
than publishing a page that only differs by its title.

## Replacing a generated page

Any generated page can be replaced by writing a page file at the same slug:
`content/pages/services/gutter-cleaning/springfield.json` takes over from the
generated version. Use that when an area earns real, bespoke content.
