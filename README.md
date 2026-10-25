# Copper Eagle

Marketing website for Copper Eagle, built on the reusable home-service website
platform: an owner CMS, a versioned content API, governed page templates and
blocks, lead capture, and an affiliate referral program.

This install starts clean. There are no pages beyond a placeholder homepage, no
service or area records, and no business details yet. See
[Starting the site](#starting-the-site).

## Stack

- Laravel 13 and PHP 8.5
- Livewire 4 and Blade
- Tailwind CSS 4 and Vite 8
- SQLite
- Docker for local development
- FrankenPHP production image for Dokploy

## Local development

The development container installs dependencies, prepares the local SQLite
database, runs migrations, and starts both Laravel and Vite.

```bash
docker compose up --build
```

Open [http://localhost:8012](http://localhost:8012). Vite is exposed on port
5175 for hot module replacement. These ports differ from other sites on the
platform so they can run side by side.

Run the quality gates inside the development container:

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app composer validate --strict
docker compose exec app npm run build
```

## Affiliate referral program

Permanent links use `/referrals/{code}`. An unused code opens registration for
first name, last name, phone, email, business name, optional service areas,
and a main contact from the business's team. The first successful submission claims the code
atomically and displays a welcome page
with the affiliate's reusable link. Later visits redirect to the homeowner form.
Existing registrations from the first version remain valid after migration.

Referral links open the homeowner form set in `REFERRAL_FORM_URL`. There is no
default: set it before issuing cards. It can be changed later without reprinting
cards, and it must be an absolute HTTPS URL. Existing destination query parameters are preserved, with these two
values supplied from the saved affiliate (spelling is intentional):

- `refferral_source`: full name with whitespace replaced by underscores, e.g. `Dana_Reyes`
- `refferral_business`: business name, URL-encoded

These are custom parameters, not standard `utm_*` parameters. The homeowner form
must capture these exact keys. Main-contact attribution is saved locally and on
the affiliate's HighLevel contact, not added to the homeowner URL. Names and
businesses can collide: downstream automation must account for this limitation
when attributing a homeowner to an affiliate. This application does not track
homeowner form submissions, commissions, or payouts.

### Setup

1. Run `composer install`, `php artisan migrate`, and `npm run build`.
2. Set `APP_URL` to the permanent public Copper Eagle origin **before generating
   print cards**. Generated cards store this full URL, independent of visitor Host
   headers. Changing `APP_URL` will not rewrite previously issued card URLs.
3. Configure the server-side variables in `.env.example`:
   - `GHL_PRIVATE_INTEGRATION_TOKEN` and `GHL_LOCATION_ID` for the Copper Eagle
     sub-account, with `users.readonly`, `contacts.write`, `contacts.readonly`,
     and `locations/customFields.readonly` for automatic field discovery.
   - Optional `GHL_EXCLUDED_USER_IDS`: comma-separated user IDs to hide from the
     main-contact list. Leave empty to include every active user in the location.
   - Optional `GHL_COMPANY_ID` enables paginated `/users/search`. Without it, the
     currently supported legacy location-user endpoint `/users/` is used.
   - The following field IDs are optional overrides. When any needed ID is
     missing, the backend reads this location's Contact custom fields by exact
     key and caches the validated mapping for 15 minutes. The five TEXT keys
     are `contact.referral_code`, `contact.referral_source`,
     `contact.referral_business`, `contact.internal_sales_person`, and
     `contact.referral_url`. Their optional overrides are:
     `GHL_REFERRAL_CODE_FIELD_ID`, `GHL_REFERRAL_SOURCE_FIELD_ID`,
     `GHL_REFERRAL_BUSINESS_FIELD_ID`, `GHL_REFERRAL_MAIN_CONTACT_FIELD_ID`,
     `GHL_REFERRAL_URL_FIELD_ID`.
   - `GHL_REFERRAL_SERVICE_AREAS_FIELD_ID` optionally pins the Service Areas
     multi-select field. Otherwise the backend discovers `contact.service_areas`
     and checks that its choices match `referrals.service_areas` in
     `config/referrals.php` exactly. Set that list before launch.
     If discovery fails or the field differs, registration remains saved locally
     and sync stays pending for an admin retry.
4. Refresh Laravel configuration after environment changes (`php artisan
   config:clear` locally, or rebuild the configuration cache in production).
5. Sign into `/admin/referrals` and click **Refresh HighLevel team**. This imports
   the location's team into the local database. Excluded, deleted, and disabled
   members cannot be chosen. Refresh after personnel changes. A provider error
   retains the last valid directory. No arbitrary name-based owner exclusion is used.
6. Generate 1–100 cards per batch. Download individual PNGs or the batch ZIP.
   Each PNG includes the unique code as a readable caption beneath the QR.
   PNG generation requires GD; ZIP downloads require ZipArchive. Both extensions
   are installed in the project's Docker runtime.

The registration form remains unavailable until an eligible local team exists.
Once the directory is populated, local registration and existing redirects do
not depend on HighLevel being reachable. Missing field IDs are resolved before
contact creation, and a failed lookup never causes a partial contact submission.
Affiliate phone and email go into HighLevel's standard Contact fields;
selected service areas go into the configured custom field. HighLevel's
response is recorded separately from the local registration, so a failed or
ambiguous API call never erases the claimed card.

### HighLevel contact sync and recovery

Registration saves locally first, then makes one bounded contact-create request.
The payload contains the affiliate name, phone, email, and business, the selected
team member's ID as `assignedTo`, five attribution custom-field values, and
selected service areas when present. No existing contact is matched by name,
and no homeowner form is submitted. This creates a separate affiliate contact;
matching pre-existing prospect contacts is outside this first version's identity
contract.

The Referrals admin displays sync status and provides a retry for explicit
rejections (`failed`) or previously unconfigured registrations (`pending`).
A timeout, server error, or ambiguous success becomes `uncertain`; an interrupted
process may remain `syncing`. Wait two minutes before reconciling an interrupted
sync. Per-attempt completion checks prevent late responses from overwriting a
verified reconciliation. These states **never automatically create another
contact**. Find the affiliate by its unique referral-code custom field in
HighLevel and use **Verify existing contact** with its contact ID. Reconciliation
verifies both location and code. If no remote contact can be found, investigate
before resetting state; there is intentionally no blind "create again" button.
Provider response bodies and tokens are never exposed in sync status messages.

Existing rows without a main-contact assignment keep redirecting but are not
silently assigned to a team member or sent to HighLevel. The database migration
backfills their registration timestamps without guessing first/last-name splits.
Rolling back the program migration discards unclaimed inventory and the added
attribution/analytics columns; back up the database before any rollback.

### Analytics and smoke test

Visits count successful GET requests to the referral route, including repeat
opens and possible link-preview bots. HEAD requests, welcome-page views, form
POSTs, and admin QR previews do not add visits. They are not unique people,
physical-scan counts, or conversions.

Generate a test batch, register one card, verify the welcome page, revisit its
URL and check the two query parameters on the HighLevel form. Confirm the card
moves from unused to registered in the admin and the visit counter increments.
Then verify the affiliate's mapped custom fields in HighLevel separately.
Use `php artisan test --filter=Referral` for feature checks, including decoding
PNG/ZIP contents and testing provider failures without making live API calls.

API references: [HighLevel contact creation](https://marketplace.gohighlevel.com/docs/2021-07-28/ghl/contacts/create-contact/),
[location team](https://marketplace.gohighlevel.com/docs/2021-07-28/ghl/users/get-user-by-location/),
[user search](https://marketplace.gohighlevel.com/docs/2021-07-28/ghl/users/search-users/).

## Production image

Production is intentionally separate from the development workflow:

```bash
docker build -f Dockerfile.production -t copper-eagle-production .
```

The multi-stage image compiles Vite assets, installs Composer packages without
development dependencies, serves Laravel with FrankenPHP on port 8080, runs
migrations at startup, and reports health through `/up`.

For the exact repository, environment, volume, domain, verification, and
rollback settings, see [Dokploy deployment](docs/deployment/dokploy.md).

## Discovery endpoints

- `/robots.txt`
- `/llms.txt`
- `/sitemap.xml`
- `/up`

Business content is intentionally centralized in `config/site.php` so
verified contact and service-area details can be changed without rewriting the
page templates.

## Starting the site

Nothing below is filled in yet. Each item is a decision for the owner, not
something to guess.

1. **Business details.** Name, description, phone, email, address and business
   type live in `config/site.php`; the owner can also edit them in the admin
   under Site details.
2. **Brand.** Replace the placeholder copper palette in `config/theme.php` and
   `resources/css/theme.css` (the admin Theme screen can override it at
   runtime), upload a logo, and replace the placeholder icons in `public/`.
3. **Voice.** Write `docs/brand/voice.md` with the owner before any marketing
   copy.
4. **Content.** Add service and area records under `content/` (each folder's
   README explains the format) and publish pages through the CMS or the content
   API. Legal pages must come from the owner or a legal review.
5. **Integrations.** HighLevel, referral form, mail, tracking and the canonical
   host are all unset. See `.env.example` and `docs/deployment/dokploy.md`.

Create the first admin account with:

```bash
docker compose exec app php artisan admin:account
```

The local template gallery at `/templates` shows every block with fictional
sample content. The sample photos in `public/images/home` are Pexels stock
images used only by that gallery.
