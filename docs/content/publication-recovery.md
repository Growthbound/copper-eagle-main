# Content publication recovery

Use this runbook when content is missing, wrong, stuck on a schedule, or rejected
by the platform. Preserve the `request_id`, entry ID, revision ID, lock version,
and failing response before changing anything.

## First response

1. Stop automated retries if they are changing the request under one
   `Idempotency-Key`.
2. Read the entry and immutable revision history.
3. Run:

   ```bash
   php artisan content:check
   ```

4. Confirm the public page is reading the intended `published_revision_id`.
5. Decide whether the safest action is correction, cancel-schedule, archive, or
   rollback.

Do not edit database rows or old revisions.

## Validation failure

For `request_validation_failed`, correct the JSON pointer in the HTTP envelope.
For `content_validation_failed`, correct the pointer in the managed payload.
Missing required evidence is not permission to fabricate it.

Save a new revision, issue a new preview token, review the rendered result, and
publish only that reviewed revision.

## Stale edit

`content_conflict` means another actor changed the entry after the caller read
it. GET the entry and latest revision, compare the changes, and reapply only the
still-valid intent. Retry with:

- the new `If-Match` lock version;
- a new `Idempotency-Key`;
- the same traceable `X-Request-ID` family.

Never blindly resend the stale payload.

## Idempotency conflict

`idempotency_conflict` means the key was used with a different method, path,
body, query, or `If-Match`. If the original request is still intended, send the
exact original request. If the intent changed, use a new idempotency key.

## Scheduled publication did not run

Inspect the entry's `scheduled_revision_id`, `scheduled_for`, status, and audit
events. Then run:

```bash
php artisan content:publish-scheduled
```

The command reports success or failure without publishing invalid content. If
timing or revision is wrong, use the API `cancel-schedule` action or the owner
CMS **Cancel schedule** control, preview the correct revision, and schedule it
again.

## Wrong content is live

If a clearly known-good revision exists, use **History → Rollback** or:

```text
POST /api/content/v1/entries/{entry}/rollback
```

Send its revision ID, a new idempotency key, and the current lock version.
Rollback creates and publishes a new immutable revision. Read the entry and
public page afterward.

If no revision is safe, archive the entry. Restore returns it to draft only.

## Broken media

Search the **Media** library or `GET /api/content/v1/media`. Confirm the public
URL loads and the revision contains the expected media ID, width, height, and
alt text. Replace the reference in a new revision; do not overwrite history or
invent a remote URL.

## Audit trail

Content audit events retain the actor, API key, action, revision, timestamp,
`request_id`, and operation metadata. Use the request ID from the HTTP response
to join agent logs to the server-side event. A missing or ambiguous audit event
is a reason to pause publication, not bypass the lifecycle.
