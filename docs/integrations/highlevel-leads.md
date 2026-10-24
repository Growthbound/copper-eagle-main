# Direct HighLevel lead delivery

Native website enquiries can be delivered directly to a HighLevel sub-account.
Enable the connection in **Admin → Lead delivery → GoHighLevel**. This is separate
from the affiliate integration and from hosted HighLevel forms. Hosted forms do
not create a Laravel lead, so this connection applies only to native submissions.

## Spam protection

Native inquiry forms use two equipment-specific honeypots: **Furnace or air-handler
model** (`equipment_model`) and **Current air-filter size** (`filter_size`). These
labels and names avoid standard contact-profile fields. Both are visually hidden,
excluded from screen readers and keyboard navigation, and marked to discourage
autofill. Browser autofill behavior is not guaranteed by these hints. Any nonempty
value, including whitespace or `0`, receives the usual
confirmation without validation, saving a lead, consuming a submission allowance,
or dispatching email, webhooks, or HighLevel delivery. Both card and split layouts
use the same server check. Website and fax inputs are removed. Their legacy
Livewire properties accept older browser snapshots but are ignored for spam
detection, so autofill in an old tab cannot discard an enquiry. Older tabs gain
the equipment-specific traps after reloading the page.

Honeypots catch automation that fills the traps. A human or a bot that leaves both
empty can still submit a sales pitch. They do not classify message content or
remove earlier spam. Hosted HighLevel forms require their own provider controls.

## Setup

1. In the intended HighLevel sub-account, create a Private Integration token with
   `locations.readonly`, `contacts.readonly`, and `contacts.write` permissions.
   Contact scopes also cover contact notes and tags.
2. Enter that sub-account's location ID and token in Lead delivery.
3. Connect. The website reads the location and displays its name before accepting
   the credentials. No test contact is created. This verifies location access;
   the first delivery verifies contact and note write permissions.
4. Submit one authorized enquiry, then verify its delivery status and its contact
   in HighLevel. Confirm the contact, tag, source, and submission note.

The token is encrypted using Laravel's `APP_KEY`, excluded from model JSON, and
never filled back into the admin form. Preserve the application key and encrypted
database backups together. Remove key clears the saved token and pauses delivery.
Reconnect verifies a replacement key. A stale settings tab must reload before
changing the connection. An in-flight request can finish after pause or removal.

## Delivery contract

- Contact upsert uses API `Version: v3`, the verified location ID, name, supplied
  email/phone, and captured source. `createNewIfDuplicateAllowed` is false.
  HighLevel controls duplicate matching according to the location's email/phone
  precedence. A match updates those supplied contact fields, including source;
  omitted contact fields and DND settings are not sent.
- The separate Add Tags call adds `Website Lead`, preserving existing tags.
- A note records the service, message, submission time, consent wording/time when
  recorded, source, referrer, landing page, UTMs, and click IDs. Consent metadata
  is historical evidence; the integration does not change contact DND settings.
- One durable delivery row is created per saved lead once a connection exists,
  including while paused. Each row pins its location. Existing rows prevent
  changing the connection to another account; an account migration is separate
  work. Leads saved before connecting have individual Send to HighLevel controls.
  Connecting never imports all historical records.
- A lead is `synced` only after contact, tag, and note responses are validated.
  Partial deliveries keep the contact ID and completed stages. Retries validate
  that contact still belongs to the pinned location. A database lease prevents
  overlapping sends. A note write is marked before calling the provider.
- HTTP 429, transport failure, and 5xx responses retry safe stages with increasing
  delays, up to five attempts. Authentication/validation failures stop for an
  owner retry after correcting the cause. Provider bodies and exception details
  are not stored, avoiding token and customer-data leakage into error messages.
- A lost note outcome becomes `needs review`. Check HighLevel note reads the
  contact's notes and accepts exactly one matching submission reference. It never
  automatically reposts an uncertain note. Retry missing note requires the owner
  to first inspect the contact and confirm the note is absent; a late provider
  write can still make that explicit retry duplicate a note.

## Runtime and recovery

The initial attempt runs after the HTTP response. Automatic retries need Laravel's
scheduler (`php artisan schedule:work` or an every-minute `schedule:run`). The
production Dockerfile starts the web server only; verify/add a scheduler service
or Dokploy scheduled job before promising automatic recovery. The existing
Compose scheduler consumes the new command automatically.

`php artisan highlevel:sync-leads` processes at most ten due pending records per
run. It does not create rows for old leads. Admin controls can retry individual
failures without a scheduler. Paused delivery retains pending rows. Delivery
history lives exactly as long as its lead and is removed by the lead's cascading
delete. Deleting a local lead does not delete the HighLevel contact or note; an
already-started provider request can finish. No customer export is retained.

The migration only adds the connection and delivery tables. Rollback removes
them, including the encrypted key and delivery history, while preserving leads
and existing webhooks. Prefer rolling back application code and pausing delivery
with the tables retained; contacts already created in HighLevel remain there.

## Deployment proof

Local mocked-provider tests prove application behavior, not live API acceptance.
Deploy, configure a scoped token for the intended account, prove the scheduler,
and read back one authorized contact before calling the connection live.
Publishing code, deploying, and adding production credentials require the owner's
explicit authority. Ticket logging is blocked if the Local Business Pro helper
cannot verify access; do not use another tracker.

API contracts: [upsert](https://marketplace.gohighlevel.com/docs/ghl/contacts/upsert-contact),
[add tags](https://marketplace.gohighlevel.com/docs/ghl/contacts/add-tags),
[create note](https://marketplace.gohighlevel.com/docs/ghl/contacts/create-note),
[location](https://marketplace.gohighlevel.com/docs/ghl/locations/get-location).
