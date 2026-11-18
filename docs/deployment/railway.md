# Deploy Copper Eagle With Railway

Railway runs one container per service, so this is the single-container deploy:
the same shape as Dokploy's Application type (`docs/deployment/dokploy.md`),
with Railway's own volume and port behavior accounted for.

## Service settings

| Setting | Value |
|---|---|
| Builder | Dockerfile |
| Dockerfile path | `Dockerfile.production` |
| Docker build stage | `runtime` |
| Target port | `8080` |
| Healthcheck path | `/up` |
| Start command | none — the image's `CMD` already runs FrankenPHP |
| Replicas | 1 |

The container always listens on `8080` — `docker/production/Caddyfile` binds it
literally and reads no `PORT` variable — so the target port has to be `8080`.
Railway injects `PORT=8080` at runtime, which agrees with it, but nothing in the
image depends on that. Leave the start command empty: the image's `CMD` already
passes the right arguments to the entrypoint.

Keep it at one replica. Two containers would share neither the SQLite database
nor the file cache, and both would run migrations and the scheduler.

## Volumes

Railway mounts one volume per service, so the database and the media library
share it:

| Volume mount path | Holds |
|---|---|
| `/data` | The SQLite database and, via `STORAGE_ON_DATA_VOLUME`, `storage/app` |

`STORAGE_ON_DATA_VOLUME=true` makes the entrypoint point `storage/app` at
`/data/app` and leave a symlink behind, so uploaded media survives a redeploy on
a single mount. Without it, `/data` keeps the database and every uploaded image
is gone the next time the service deploys — the site renders, and its media
library is empty.

`RAILWAY_RUN_UID=0` is required. Railway mounts volumes as root, and the image
runs as `www-data`, which could otherwise write neither the media library nor
the SQLite file. The entrypoint uses that one privilege to take ownership of
`/data`, `/app/storage` and `/app/bootstrap/cache`, then drops back to
`www-data` for the rest of startup and for the web server itself. The app does
not serve traffic as root.

What stays ephemeral is the rest of `/app/storage`: the file cache, the compiled
views, and `storage/framework/sessions`. Losing the cache and views costs
nothing — startup rebuilds them — but signed-in admins are logged out by a
deploy, because their session files go with the old container. Logs are
unaffected; they go to stderr and Railway collects them.

On a host that allows two volumes, mount the second at `/app/storage` and leave
`STORAGE_ON_DATA_VOLUME` unset; that matches the Compose deploy exactly, and
sessions then survive a deploy too.

## Variables

Paste this into the service's Raw Editor, then fill in `APP_KEY`, `APP_URL`,
`RESEND_API_KEY` and a verified sender address. Generate the application key with
`php artisan key:generate --show` and keep the same one across redeploys;
rotating it invalidates every encrypted cookie.

```dotenv
APP_NAME="Copper Eagle Plumbing"
APP_KEY=base64:replace-with-a-generated-key
APP_URL=https://replace-me.up.railway.app
SITE_CANONICAL_HOST=
APP_TIMEZONE=America/Denver
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
LOG_LEVEL=info
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
RUN_SCHEDULER=true
STORAGE_ON_DATA_VOLUME=true
RAILWAY_RUN_UID=0
MAIL_MAILER=resend
RESEND_API_KEY=replace-with-a-resend-api-key
MAIL_FROM_ADDRESS=notifications@mail.youragency.com
MAIL_FROM_NAME="Copper Eagle Plumbing"
LEAD_NOTIFICATION_RECIPIENTS=diamond9utah@gmail.com
WEBSITE_FORM_PROVIDER=native
WEBSITE_HIGHLEVEL_ORIGIN=https://api.leadconnectorhq.com
TRACKING_GTM_CONTAINER_ID=GTM-TPNKWP37
TRACKING_GA4_MEASUREMENT_ID=
TRACKING_CTM_ACCOUNT_ID=581672
TRACKING_CANONICAL_HOST_ONLY=true
```

`APP_ENV`, `APP_DEBUG`, `DB_CONNECTION`, `DB_DATABASE` and `LOG_CHANNEL` are set
by the image. Setting them here can only break the deploy: the entrypoint
refuses a truthy `APP_DEBUG` in production and a SQLite path outside `/data`.

`RUN_SCHEDULER=true` matters on Railway the same way it does for a Dokploy
Application: there is no separate scheduler service, so the web container runs
`schedule:work` itself for scheduled publishing and nightly cleanup.

`QUEUE_CONNECTION=sync` matters too — without it, queued work such as HighLevel
lead delivery waits for a worker that never runs.

### Website notification mail

Use Resend's HTTPS API on Railway. [Railway blocks outbound SMTP on Free,
Trial and Hobby plans](https://docs.railway.com/networking/outbound-networking),
so SMTP credentials alone cannot deliver notifications on those plans. The
production Composer dependencies include the SDK required by Laravel's native
`resend` mailer.

1. Add and verify a sending domain in Resend. Prefer a dedicated sending
   subdomain; add only its issued DNS records and preserve existing mailbox MX,
   SPF and DKIM records.
2. Set `MAIL_MAILER=resend`, `RESEND_API_KEY` to an API key allowed to send from
   that domain, and `MAIL_FROM_ADDRESS` to an address on the verified domain.
   Resend's `resend.dev` domain is restricted to sending to the Resend account
   owner's email; it is not a production sender for arbitrary recipients.
3. Deploy the code containing the SDK before activating these variables. SMTP
   host, port, username and password settings are unused by the API mailer.
4. Check Admin → Notifications: saved recipients override
   `LEAD_NOTIFICATION_RECIPIENTS`. After approving a test recipient, run
   `php artisan mail:test recipient@example.com` in the Railway Console. It
   sends one synthetic notification without creating a lead. Check Resend's
   delivery status and the destination inbox; provider acceptance alone does
   not prove arrival.

This configures website notifications independently of the business's mailbox
provider. Do not replace the root domain's mailbox records with Resend records.

The referral program (`REFERRAL_FORM_URL`, every `GHL_*`) and the HighLevel form
IDs are optional; `.env.production.example` lists them all, and anything left
blank stays off.

## Hostnames

`APP_URL` must name the host Railway actually serves. Point it at the live
domain while the service answers on `*.up.railway.app` and the site will send
visitors to the old domain.

`SITE_CANONICAL_HOST` stays blank until cutover. Blank means every host returns
`noindex`, which is what a preview host should do, and the app boots fine
without it — `App\Support\SearchVisibility` treats an empty value as unset and
fails closed. At cutover, set it to the public hostname and change `APP_URL` to
match; they must name the same host or boot fails with the mismatch printed.

## After the first deploy

1. `curl -s https://<host>/robots.txt` — a preview host must print
   `Disallow: /`. Once `SITE_CANONICAL_HOST` is set it must print `Allow: /` and
   a `Sitemap:` line.
2. Upload an image in the admin, then redeploy and confirm it is still there.
   That is the check that `STORAGE_ON_DATA_VOLUME` and the volume mount are both
   doing their job.
3. Submit the contact form and confirm the lead email arrives.
4. Confirm the deploy logs show migrations running once and no scheduler
   duplication.
