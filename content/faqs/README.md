# Questions

One JSON file per question the business is actually asked.

Questions render as an accordion and are emitted as `FAQPage` structured data
from the same records, so the markup can never describe something the page does
not show.

## Fields

| Field | Required | Notes |
|---|---|---|
| `question` | yes | Phrased the way a customer would ask it |
| `answer` | yes | Two or three sentences. Plain text, no markup |
| `order` | no | Lower sorts first |
| `services` | no | Service slugs this applies to. Omit for general |
| `areas` | no | Area slugs this applies to. Omit for general |

## Scoping

A record with neither `services` nor `areas` is **general**: it appears on the
homepage and on every service and area page.

Adding `services` narrows it. A question scoped to `gutter-cleaning` shows
on that service's pages and nowhere else — general questions still show
alongside it, so there is no need to repeat "how long does a visit take?" in
every record.

```json
{
    "question": "How do I know my gutters need cleaning?",
    "answer": "Water spilling over the edge during rain is the usual first sign.",
    "services": ["gutter-cleaning"],
    "order": 1
}
```

Naming a service or area with no record is an error, not a warning —
`content:check` will fail.

## Worth knowing about rich results

Google restricted FAQ rich results in 2023 to a small set of government and
health sites, so this markup will **not** put expandable questions in Google's
results for a home services site. It is still worth emitting: other search
engines and AI answer engines read it, it costs nothing, and the on-page
questions earn their place regardless of what the markup does.

Write questions because customers ask them, not because of the markup.
