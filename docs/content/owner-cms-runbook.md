# Owner content CMS runbook

The owner CMS at `/admin/content` is the normal human control surface. It uses
the same validator and lifecycle service as Hermes, so neither lane can bypass
the page contract.

## Daily publishing workflow

1. Open **Content** and filter or search for the entry.
2. Open the editor. Identity fields control kind, slug, title, and page
   template. Fixed templates expose their required sections in safe order.
3. Edit structured fields. Rich content supports headings H3–H6, paragraphs,
   ordered and unordered lists, inline links and emphasis, Images, callouts,
   quotes, and dividers. Raw HTML is intentionally unavailable.
4. Use **Media** to find or upload the approved asset. Insert it through the
   editor so its media ID, alt text, width, and height stay connected.
5. Save a draft revision with a useful summary.
6. Select the revision and use **Preview**. The Preview URL expires; do not use
   it as a public link.
7. Resolve every validation error and evidence warning. Check the page at
   desktop and mobile widths.
8. Use **Publish** for an immediate release or **Schedule** with the intended
   timezone-aware date.
9. Reopen the public URL and confirm the published title, H1, media, links,
   CTA, and structured content.

Saving is not publishing. That distinction is deliberate.

## Template choice

- `standard`: flexible pages assembled from registered blocks.
- `service-pillar`: conversion-first service page with complete decision
  support, evidence, locations, FAQ, sources, and related content.
- `location-pillar`: locally relevant service-area page without fake office or
  thin swapped-city claims.
- `decision-guide`: editorial answer page with direct answer, table of contents,
  evidence, comparison, sources, and next step.

Changing a fixed template changes the required section contract. Do it before
writing the page, not after.

New fixed-template drafts are intentionally marked as scaffolds. They may be
saved while work is in progress, but they cannot be validated for publication,
previewed, published, scheduled, or rolled back into public state until every
placeholder is replaced and the top-level `_scaffold` marker is removed. Do
not treat the generated names, claims, prices, reviews, statistics, locations,
sources, or media as business facts. After replacing them, use **Mark scaffold
content as replaced**, save a new revision, and then run validation and preview
against that exact revision.

## History and Rollback

Open **History** from the content list or editor to see immutable revisions,
their summaries, authors, API-key attribution, and timestamps. Compare the
selected versions before acting.

**Rollback** creates a new revision from an old payload and publishes the copy.
It never destroys or rewrites the original history. Use it when a published
change is wrong and the last known-good revision is clear.

## Scheduling

**Schedule** interprets the local date/time in the configured `APP_TIMEZONE`,
validates the selected revision immediately, stores the intended
future time, scheduling owner, and API key when applicable, and changes the
entry to scheduled. A later draft does not silently replace the scheduled
revision or its audit attribution.

Use **Cancel schedule** before changing launch timing. Then preview the exact
revision again and create a new schedule. The server command
`php artisan content:publish-scheduled` publishes due revisions and leaves
failed validation unpublished.

## Archive and restore

Archive removes the entry from published state and clears any pending schedule.
The CMS blocks archival while published navigation or footer content still
links to that page; remove and publish those structure links first.
Restore returns it as a draft; it does not silently republish old content.
Preview and publish it again through the normal workflow.

## Media

The **Media** library is searchable by filename, title, alt text, and handle.
For every image:

- use a truthful, task-relevant title;
- write meaningful alt text unless the image is purely decorative;
- preserve intrinsic width and height;
- reuse the existing media record when it is the same asset;
- use image records only in image fields and video records only in video
  fields;
- do not clear required alt text while managed content references the media;
- do not delete media that has recorded revision usage.

Videos require a controlled video record, poster, caption, and transcript when
the selected block schema asks for them.

## Redirects and site structure

Use **Redirects** when a published URL changes. The source is a local path; the
target must be a safe local or approved absolute URL. Never create chains or a
redirect to itself.

Navigation and footer links are edited under the structure editor. Publish
them as managed content so the public header and footer change together and
remain reversible.

Root-relative navigation and footer destinations must already resolve to a
public page, service, area, media item, or discovery file. Create and publish a
new destination before linking to it; the editor rejects links that would
produce a public 404.

Every redirect create, update, enable/disable, and delete action writes an
immutable actor-attributed audit snapshot. Deleting the redirect does not
delete its audit history.

## API keys

`/admin/keys` shows the one editable scope set for each active key. High-impact
publish, schedule, and rollback scopes are visibly flagged. Generate separate
keys for separate jobs, rotate on exposure or personnel changes, and revoke
unused keys. Rotation revokes the old key immediately.
