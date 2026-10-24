# Website forms

The quote and contact blocks either use the site's own native form (the
default) or embed a hosted HighLevel form. This runbook covers switching a site
to HighLevel and back.

Website intake is independent of the affiliate referral program in
`config/referrals.php`. Never use the referral `GHL_*` credentials or IDs to
configure website intake. Website embeds need no private integration token.

## Deployable configuration

The platform is native by default. A HighLevel activation sets:

```dotenv
WEBSITE_FORM_PROVIDER=highlevel
WEBSITE_HIGHLEVEL_ORIGIN=https://api.leadconnectorhq.com
WEBSITE_HIGHLEVEL_INQUIRY_FORM_ID=<20-character form ID>
WEBSITE_HIGHLEVEL_FEEDBACK_FORM_ID=
WEBSITE_HIGHLEVEL_COMMENTS_FORM_ID=
```

Rebuild Laravel's configuration cache with the release's normal deployment
procedure after changing these settings. The origin must exactly match the
approved origin allowlist; IDs must be 20 alphanumeric characters. Invalid or
missing settings show the configured telephone alternative, never native intake.
Feedback/comments are independent profiles. Do not configure them with the
inquiry ID. HighLevel mode disables real native Livewire submission server-side,
including a tab opened before activation. Native code and historical leads stay
in place. **HighLevel submissions do not populate the local lead inbox, native
notification email or `lead.created` webhooks.** No dual-write bridge is included.

Both quote blocks accept optional `purpose`: `inquiry` (default), `feedback`, or
`comments`. Provider URLs, IDs and scripts are not managed-content properties.
`config/website-forms.php` can map a legacy page path to a purpose for revisions
published before `purpose` existed. Native mode only supports inquiry; the other two
purposes never fall back to sales. Template and managed previews render a local,
non-persisting native demonstration with no provider iframe or script.

## Attribution and embedding

The same hosted form retains provider-owned fields, consent controls, captcha,
validation and inline confirmation. It has first name, last name, email, phone,
service choices, city and notes. Do not recreate fields or translate service
values locally. HighLevel controls the wording and confirmation. For the
service-related A2P flow, its contact-consent control must be separate, optional,
unchecked by default and accompanied by the complete disclosure and both legal
links. A repository deploy cannot change that cross-origin form.

Only five standard `utm_*` fields plus `gclid`, `fbclid`, `msclkid` are forwarded.
Explicit current-page values take precedence; missing keys use first-touch
session values. Malformed, oversized (>255 characters), empty or control-character
values are discarded, including before storage for subsequent navigation.
Arbitrary query fields, full landing/referrer URLs, private tokens and referral
parameters are not passed to the provider. Sessions existing before release can
reuse their already-bounded native attribution. Personalized iframe responses are
`private, no-store`; the CDN must respect or bypass caching for these pages.

**Implementation adjustment from the original plan:** the audited public
`form_embed.js` copies parent query parameters and parent context through
postMessage. Loading it would bypass the bounded query contract. The renderer
therefore uses the hosted form directly, with a 1000px viewport, native scrolling,
and a same-destination new-tab alternative. It does not install a resize or
success-message listener. This trades automatic sizing and helper-supplied
parent context for bounded data transfer. CRM attribution and submission behavior
must be checked on staging before activation; URL presence alone is not proof.

Existing block anchors remain unchanged. With JavaScript, a homepage
`/#contact-us` link scrolls to the first form section if no real `contact-us`
anchor exists. No additional duplicate IDs are inserted.

No redirect replaces the inquiry form's existing inline confirmation. Do not
record iframe load, page view or button click as a successful conversion.

## Release checklist: required before production activation

1. In the **website account**, read existing workflow form filters, service-value
   branches, tags, pipeline/stage, assignment, notifications, wait/goal steps and
   confirmation behavior. Save a sanitized baseline. Do not modify workflows.
2. Confirm the destination for every form the site needs (inquiry, feedback,
   comments), including fields and recipients. Obtain approval before creating
   provider forms. A contact alternative is not a completed migration of a
   channel.
3. Fetch the deployed `/api/content/v1/schema`; inventory actual published form
   blocks and anchors. Use the CMS/API to draft any `purpose=feedback` or
   `purpose=comments` blocks. Validate and preview immutable revisions. Keep
   lock versions/revision IDs for separately authorized publication. Do not
   reseed or directly edit published rows.
4. Configure staging, verify canonical-host noindex behavior and inspect inquiry,
   feedback, service/area and multi-form pages at 1440px, 768px and 390px. Test
   keyboard access, nested scrolling, blocked-provider fallback and direct link.
5. With explicit authority for a test lead, submit a labeled lead for each
   approved purpose. Read it back in the intended HighLevel location; confirm
   workflow enrollment, notification recipient, pipeline, assignment, service
   branch, consent, first/last attribution and inline confirmation. Remove any
   test records only under separate authority. Do not use a real customer's data.
6. Compare the site's tracking to the approved baseline. Presence is not
   conversion proof. Do not blindly duplicate tags; prove exactly one intended
   conversion for a successful submission and none for validation failure or
   page load.
7. Confirm CDN cache exclusion, legal links and all legacy destinations. Publish
   approved CMS revisions and activate production only with explicit approval.
   Repeat readback and monitor provider delivery against the baseline.

If the site sends texts (A2P 10DLC), submit the form once with consent selected
and once with consent declined, using approved synthetic test data. Confirm both
inquiries arrive, the consent state and wording version are distinguishable, and
only the opted-in record can enter SMS or automated/artificial-voice workflows.
A required or preselected contact checkbox is a release blocker.

## Recovery and eventual native migration

If delivery fails during cutover, revert to the last verified site/release and
configuration. Do not silently switch to native: its downstream delivery differs.
Keep the old site available until provider delivery and conversions are verified.

When the owner chooses to migrate away from HighLevel, verify native inbox,
notifications, webhooks, consent, retention and follow-up first; then set
`WEBSITE_FORM_PROVIDER=native` and rebuild config cache. Native inquiry uses its
retained source and validation. Feedback/comments need their own native workflow
before activation. Historical native lead records remain available throughout.
