# Services

One JSON file per service the business offers. The filename is the slug, so
`gutter-cleaning.json` is served at `/services/gutter-cleaning`.

Adding a file publishes: a card on the homepage, an option in the enquiry
form's dropdown, its own page, a page for every service area, and sitemap
entries for all of them. Nothing else needs updating.

## Fields

| Field | Required | Notes |
|---|---|---|
| `name` | yes | As shown everywhere. Also the dropdown label |
| `summary` | yes in practice | One sentence, used on cards and as the page description |
| `description` | no | A paragraph for the service page. Falls back to `summary` |
| `highlights` | no | Short strings rendered as a checklist |
| `order` | no | Lower sorts first. Unordered services fall to the end alphabetically |
| `image` | no | `src`, `alt`, `width`, `height`. `src` is a path under `public/` |
| `areas` | no | Area slugs this is offered in. Omit for everywhere |

## Example

```json
{
    "name": "Gutter Cleaning",
    "summary": "Gutters and downspouts cleared so rain runs away from the house.",
    "description": "A longer paragraph for the service page.",
    "highlights": ["Every downspout flushed", "Before and after photos"],
    "order": 2,
    "image": {
        "src": "images/home/technician-controls.jpg",
        "alt": "Technician inspecting a gutter",
        "width": 1600,
        "height": 2844
    }
}
```

Only set `areas` when a service genuinely is not offered everywhere — listing
an area slug that has no record in `content/areas` is an error, not a warning.
