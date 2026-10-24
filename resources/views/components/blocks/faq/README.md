# FAQ

Accordion list of questions and answers.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `items` | array | yes | List of `['question' => ..., 'answer' => ...]` |

## Variants

| Variant | Layout |
|---|---|
| `accordion` | Collapsible list. Defaults to the records in `content/faqs`. |
| `highlighted` | Single-open rounded stack with a themed active row. |

## Notes

Build the accordion with `<details>` and `<summary>` rather than JavaScript.
It is keyboard accessible for free, works without JS, and search engines read
the answer text because it is present in the markup.

Questions come from `content/faqs` by default, narrowed to the page's service
or area, and the matching `FAQPage` markup is emitted from those same records —
so the page and the markup cannot disagree. Pass `items` only for a one-off
list that does not belong in the records.

`highlighted` also accepts `body` and `openIndex`. `openIndex` is zero-based,
defaults to `0`, and may be `null` when every item should start closed.
