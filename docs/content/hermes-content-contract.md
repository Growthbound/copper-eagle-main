# Hermes content contract

This is the machine contract for any Hermes agent that creates or changes this
site's content. The live schema at `/api/content/v1/schema` is authoritative when
it differs from this document.

## Non-negotiable rules

- Use HTTP Basic authentication. The username is the API-key owner's email and
  the password is the one-time API key shown under `/admin/keys`.
- Give each key only the scopes needed for its job. Never place a real key in
  source control, logs, prompts, or documentation.
- Send a unique `Idempotency-Key` on every state-changing request. A key is
  unique across every operation for that API credential. Reusing it with the
  same request safely replays the original status, JSON body, and `ETag`;
  changing the request or operation returns `idempotency_conflict`.
- Send `If-Match: "<lock_version>"` on writes to an existing entry. A stale
  version returns `content_conflict`; read the entry again before retrying.
- Send a stable `X-Request-ID` for traceability. The API echoes it and records it
  in the audit event. It must be 8–100 letters, numbers, periods, underscores,
  colons, or hyphens.
- Content is structured data, never raw HTML, JavaScript, CSS, SVG, Blade,
  component names, or arbitrary classes.
- A full page has exactly one H1. A standard hero owns it when present;
  otherwise the standard page renderer creates it from the page title. Block
  headings are H2; rich-content headings progress sequentially through H3–H6.
- Creating or revising content makes a draft. It does not publish. Preview,
  publish, schedule, and rollback are separate operations.
- A preview token is a short-lived secret. Share its URL only with the intended
  reviewer and never persist it as public content.
- Do not invent reviews, credentials, statistics, project outcomes, locations,
  prices, health claims, or media evidence.

## Authentication scopes

| Scope | Capability |
|---|---|
| `media:import` | Fetch approved remote media through the import endpoint |
| `media:read` | Search the managed Media library |
| `content:read` | Read schema, entries, and immutable revisions |
| `content:draft` | Create entries, revisions, and restore archived entries |
| `content:preview` | Issue a short-lived preview |
| `content:publish` | Publish or archive content |
| `content:schedule` | Schedule or cancel future publication |
| `content:rollback` | Publish a new revision copied from prior history |

Rotation copies the exact scope set and revokes the old key immediately.

## Endpoints

All endpoints are below `/api/content/v1` and are limited to 60 requests per
minute per authenticated identity.

| Method | Path | Scope | Purpose |
|---|---|---|---|
| GET | `/schema` | `content:read` | Registry, templates, rich-node grammar, and enums |
| GET | `/media` | `media:read` | Search safe media metadata |
| GET | `/entries` | `content:read` | Filtered, paginated content catalog |
| POST | `/entries` | `content:draft` | Create a validated draft and revision 1 |
| GET | `/entries/{entry}` | `content:read` | Read identity, status, lock, and latest revision |
| POST | `/entries/{entry}/validate` | `content:draft` | Validate a candidate revision without saving |
| GET | `/entries/{entry}/revisions` | `content:read` | Read immutable history |
| POST | `/entries/{entry}/revisions` | `content:draft` | Validate and append a revision |
| GET | `/entries/{entry}/revisions/{revision}` | `content:read` | Read one owned revision |
| POST | `/entries/{entry}/preview` | `content:preview` | Issue a one-hour preview token |
| POST | `/entries/{entry}/publish` | `content:publish` | Publish a selected revision |
| POST | `/entries/{entry}/schedule` | `content:schedule` | Schedule a selected revision |
| DELETE | `/entries/{entry}/schedule` | `content:schedule` | Remove a pending schedule |
| POST | `/entries/{entry}/rollback` | `content:rollback` | Copy and publish a prior revision |
| POST | `/entries/{entry}/archive` | `content:publish` | Remove an entry from public state |
| POST | `/entries/{entry}/restore` | `content:draft` | Return an archived entry to draft |

List endpoints accept `page` and `per_page`; `per_page` is clamped to 1–100.
Entries also accept `kind`, `status`, `template`, exact `slug`, and `search`.
Media accepts `kind` and `search`. Storage paths, remote source URLs, checksums,
and secret hashes are never returned.

New content may use only the `page`, `navigation`, and `footer` kinds. Public
page slugs cannot collide with application routes, including `admin`, `api`,
`_templates`, `templates`, `services`, `areas`, `media`, `storage`,
`content-preview`, `livewire`, `build`, `up`, `robots.txt`, `llms.txt`, or
`sitemap.xml`.

## Read the schema first

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Accept: application/json' \
  --header 'X-Request-ID: hermes-schema-20260725' \
  'https://example.test/api/content/v1/schema'
```

The response enumerates every allowed block field, fixed page-template
sequence, rich-content node, enum, and API scope. Each array field declares
whether it is an ordered `list` or keyed `object`, plus its nested item or
property contract. Optional renderer fields are part of this live contract;
do not infer fields from screenshots or Blade templates. Unknown blocks,
unknown fixed sections, raw markup, and wrong field shapes fail closed.

## Fixed-template scaffolds

The owner CMS can create a fixed-template draft containing placeholder sections
marked with top-level `"_scaffold": true`. That marker makes the draft
deliberately non-publication-ready:

- the draft may be saved while the owner replaces every placeholder;
- candidate validation, preview, publish, schedule, and rollback fail at
  `/_scaffold` while the marker is present;
- remove the marker only after every fictional name, claim, price, review,
  statistic, source, location, and media reference has been replaced with
  verified business content.

Never copy a scaffold into a public revision or delete required fixed sections
to make validation pass.

## Choosing a page template

Every Copper Eagle page uses one fixed template. Pick the one that matches the
page, fill each section's props from `/api/content/v1/schema`, and never add,
drop, or reorder sections: the order is the design, and it is what keeps pages
from drifting apart. The full section list per template is in
`docs/brand/design-system.md`; the live schema is authoritative.

| Template | Use it for |
|---|---|
| `home` | The home page only |
| `services-hub` | The page that lists every service |
| `service-detail` | One page per service |
| `emergency` | The after-hours / emergency service page |
| `about` | The company story |
| `team` | The people who come to the home |
| `reviews` | Customer reviews and the review prompt |
| `contact` | Contact details, the message form, and service areas |
| `decision-guide` | Blog articles |
| `standard` | One-off pages only: legal pages, thank-you pages |

`service-pillar` and `location-pillar` remain available for deep, evidence-led
service or location pages. Each template's sample content is visible in the
local gallery at `/templates` (the pages ending in "Page").

## Create a safe standard draft

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: hermes-create-about-20260725-001' \
  --header 'X-Request-ID: hermes-create-about-20260725' \
  --data '{
    "identity": {
      "kind": "page",
      "slug": "about",
      "title": "About Example Co.",
      "template": "standard"
    },
    "payload": {
      "title": "About Example Co.",
      "blocks": [
        {
          "type": "hero/interior",
          "props": {
            "heading": "A careful team for your home"
          }
        },
        {
          "type": "content/direct-answer",
          "props": {
            "heading": "What does Example Co. do?",
            "answer": [
              {
                "text": "Example Co. helps homeowners understand and improve the condition of their home through careful inspection, clear recommendations, and respectful service."
              }
            ]
          }
        }
      ]
    }
  }' \
  'https://example.test/api/content/v1/entries'
```

The response contains `data.id`, `data.lock_version`, and
`data.latest_revision.id`. Save all three. The `ETag` contains the current lock
version.

## Add a revision

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: hermes-revise-entry-42-001' \
  --header 'If-Match: "0"' \
  --header 'X-Request-ID: hermes-revise-entry-42' \
  --data '{
    "summary": "Clarify the direct answer",
    "payload": {
      "title": "About Example Co.",
      "blocks": [
        {
          "type": "content/direct-answer",
          "props": {
            "heading": "What does Example Co. do?",
            "answer": [
              {
                "text": "Example Co. helps homeowners make a clear decision about their home with careful inspection, practical explanations, and respectful service."
              }
            ]
          }
        }
      ]
    }
  }' \
  'https://example.test/api/content/v1/entries/42/revisions'
```

Identity changes are allowed only through the optional complete `identity`
object. The identity is stored with that immutable revision. If the entry is
already public, its live kind, slug, title, and template do not change until
that exact revision is published. Candidate validation and persistence both
reject a kind-and-slug path already owned by another entry. Preview, schedule,
publish, and rollback all use the selected revision's identity. Never mutate
the database directly.

## Media workflow

Search before reusing a photo:

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'X-Request-ID: hermes-media-search-20260725' \
  'https://example.test/api/content/v1/media?search=inspection&kind=image&per_page=25'
```

Use the returned `id`, public `url`, intrinsic `width`, intrinsic `height`, and
meaningful `alt` text in a schema-approved image field. Importing a remote file
is a different operation at `/api/media/import`, requires `media:import`, and
requires a unique `Idempotency-Key` plus `X-Request-ID` for each distinct
batch. Retry the same batch with the same key to replay its original ledger;
the response always echoes the current correlation ID.

## Preview, publish, and schedule

Issue a preview:

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: hermes-preview-entry-42-r3' \
  --header 'If-Match: "2"' \
  --data '{"revision_id": 103}' \
  'https://example.test/api/content/v1/entries/42/preview'
```

Publish only after reading the preview and checking evidence:

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: hermes-publish-entry-42-r3' \
  --header 'If-Match: "2"' \
  --data '{"revision_id": 103}' \
  'https://example.test/api/content/v1/entries/42/publish'
```

Schedule with an explicit ISO-8601 timestamp:

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: hermes-schedule-entry-42-r3' \
  --header 'If-Match: "2"' \
  --data '{"revision_id": 103, "publish_at": "2026-08-01T09:00:00-06:00"}' \
  'https://example.test/api/content/v1/entries/42/schedule'
```

## Errors and conflict recovery

Every error includes:

```json
{
  "message": "Managed content failed validation.",
  "code": "content_validation_failed",
  "request_id": "hermes-revise-entry-42",
  "errors": {
    "/blocks/0/props/image/alt": [
      "Image alt text is required."
    ]
  }
}
```

`errors` keys are JSON pointers. Correct the exact pointer; do not remove a
required section to silence an error. The stable status contract is:

- 401 `unauthenticated`: credentials are absent, malformed, revoked, or wrong.
- 403 `missing_scope`: the key is valid but under-scoped.
- 404 `not_found`: the entry/revision is absent or not accessible in context.
- 409 `content_conflict`: `If-Match` is stale.
- 409 `idempotency_conflict`: a write key was reused with different input.
- 500 `idempotency_response_too_large`: the write was rolled back because its
  response could not be stored safely for replay.
- 422 `request_validation_failed`: the HTTP payload shape is wrong.
- 422 `content_validation_failed`: the content violates the registry contract.
- 429 `too_many_requests`: wait for the rate-limit reset.

For `content_conflict`, GET the entry, compare its latest immutable revision,
reapply only the still-valid change, and POST with a new `Idempotency-Key` and
the new lock version.

## Rollback

Rollback never edits history. It copies a chosen historical payload into a new
revision and publishes that new revision:

```bash
curl --fail-with-body \
  --user 'owner@example.com:YOUR_API_KEY' \
  --header 'Content-Type: application/json' \
  --header 'Idempotency-Key: hermes-rollback-entry-42-to-r1' \
  --header 'If-Match: "6"' \
  --data '{"revision_id": 97}' \
  'https://example.test/api/content/v1/entries/42/rollback'
```

Read the entry afterward and verify the new `published_revision_id`,
`lock_version`, identity, and public page.

## Website form purposes

`cta/quote-form` and `cta/quote-form-split` accept optional string `purpose`,
one of `inquiry`, `feedback`, `comments`; omission means `inquiry`. The live
schema lists this enum. Destinations and provider configuration are server-owned
and cannot be submitted as block fields. Native presentation props apply only
to native/demo forms. Previews never load a provider form. The legacy Feedback
URL is held to the feedback purpose even for older revisions. See
[website forms runbook](website-forms-runbook.md) for activation and delivery gates.
