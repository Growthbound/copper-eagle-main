# Deploy Copper Eagle With Dokploy

## Deployment source

Use these values when creating a Docker Compose service in Dokploy:

| Setting | Value |
|---|---|
| Provider | GitHub |
| Repository | `Growthbound/copper-eagle-main` |
| Branch | `main` |
| Compose path | `./compose.production.yaml` |
| Application service | `app` |
| Container port | `8080` |

Enable Dokploy's isolated deployment mode. Do not add `container_name`, host
port bindings, or manual Traefik labels; Dokploy's native Domains UI supplies
the routing network and labels during deployment.

## Required environment variables

Add all required variables in the Dokploy Compose environment before the first
deploy:

```dotenv
APP_KEY=base64:replace-with-a-generated-key
APP_URL=https://your-production-domain.example
SITE_CANONICAL_HOST=your-production-domain.example
APP_TIMEZONE=UTC
MAIL_MAILER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your-ses-smtp-username
MAIL_PASSWORD=your-ses-smtp-password
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=notifications@mail.youragency.com
MAIL_FROM_NAME="Copper Eagle"
```

`SITE_CANONICAL_HOST` is the single hostname allowed into search indexes, and it
is the hostname only — no scheme, no path, no port — because Caddy compares it
literally when serving static files and cannot normalize it, and because none of
the values it is checked against carry a port either. Anything else is refused,
and a refused value indexes nothing, so a malformed entry here shows up as a
site missing from Google rather than as a subtle mismatch. It must also name the
same host as `APP_URL`; boot fails with the mismatch printed if it does not.
Every other host serving this application returns `noindex`, both as a meta tag
and as an `X-Robots-Tag` header so it also covers the sitemap and the images.

Its robots file is deliberately not a blanket block. Search crawlers are allowed
in, because a crawler that cannot fetch a page never sees the `noindex` on it and
keeps whatever it already holds. Answer-engine crawlers are disallowed by name —
listed in `config/search.php` — because a training corpus has no retraction, so
the only useful moment to refuse is before the fetch. The sitemap is not
advertised on a non-canonical host.

It has no default. The Compose file refuses to start without it rather than
letting the app come up quietly de-indexed, so a missing value fails the deploy
instead of the rankings. After the first deploy, confirm it took effect:

```bash
curl -s https://your-production-domain.example/robots.txt
```

That must print `Allow: /` and a `Sitemap:` line. If it prints `Disallow: /`,
the variable does not match the hostname the site is actually served on.

Generate `APP_KEY` from the local development container:

```bash
docker compose exec app php artisan key:generate --show
```

Store the printed value only in Dokploy. Do not commit it to Git. Keep the same
key across redeployments; rotating it invalidates encrypted cookies and any
other data encrypted with the previous key.

`compose.production.yaml` fixes the remaining production defaults, including
`APP_ENV=production`, `APP_DEBUG=false`, stderr logging, SQLite, file sessions,
file cache, and synchronous queues. `SESSION_SECURE_COOKIE` defaults to `true`.
`APP_TIMEZONE` defaults to UTC when omitted. The SMTP transport credentials and
sender values above are intentionally required so a deployment cannot silently
ship without an operational sender.

## Domain and HTTPS

After the first successful build:

1. Open the Compose service's **Domains** tab.
2. Add the production hostname.
3. Select service `app` and container port `8080`.
4. Enable HTTPS and certificate issuance.
5. Redeploy the Compose service so Dokploy applies its generated Traefik labels.

Laravel trusts Traefik's forwarded headers, so HTTPS URLs and secure cookies
remain correct even though Traefik connects to the container over HTTP.

The Compose project also starts the `scheduler` service. It runs Laravel's
minute scheduler against the same database and storage volumes as `app`, so
scheduled content publishes automatically. Do not attach a domain or expose a
port for `scheduler`.

The scheduler also removes expired API idempotency records and expired or
revoked preview tokens every night. Those credentials are operational
ephemera; immutable content revisions and audit events are never pruned.

## Persistent state

The Compose service creates two named volumes:

| Volume | Container path | Purpose |
|---|---|---|
| `database_data` | `/data` | SQLite database and Caddy's local runtime cache |
| `storage_data` | `/app/storage` | Laravel logs, sessions, cache, and uploaded media |

Admin media uploads land in `/app/storage/app/public` and are served through the
`public/storage` symlink that the image creates at build time. Because that
directory lives on the `storage_data` volume, uploaded images survive redeploys.

Back up both volumes before destructive schema or application changes. A normal
redeploy replaces the image but retains these volumes. The `app` startup script
runs `php artisan migrate --force --no-interaction` and `php artisan optimize`
before FrankenPHP begins accepting traffic. The `scheduler` waits for the
application health check, skips migrations, and then runs
`php artisan schedule:work`.

SQLite makes this deployment intentionally single-replica. Move the database
to PostgreSQL or MySQL and generated files to shared object storage before
enabling horizontal application replicas.

## Creating the owner account

The site has no public registration route. Create the single admin account once,
after the first successful deploy, from Dokploy's terminal for the running
container:

```bash
php artisan admin:account --email=owner@example.com --name="Site Owner"
```

That prompts for a password (minimum 12 characters). On a console without
interactive input, pass `--password=` instead. Running the same command again
for an existing email resets that account's password rather than creating a
second one.

The owner then signs in at `/admin` to read form submissions, upload media,
manage API keys, and change the site colors.

## Managed API keys

Create integration credentials after the owner account exists:

1. Sign in and open `/admin/keys`.
2. Enter a specific name such as `Local media migration` and select **Generate key**.
3. Copy the full `site_live_...` value immediately. The application stores only its hash and cannot show the plaintext again.
4. Use the owner email as the HTTP Basic username and the generated key as the password.
5. Keep the plaintext in the local tool's uncommitted `.env`; never add it to Dokploy, Git, logs, command output, or application settings.

For the planned local WordPress migration client, the local-only values are:

```dotenv
MEDIA_IMPORT_API_URL=https://your-production-domain.example/api/media/import
MEDIA_IMPORT_API_USER=owner@example.com
MEDIA_IMPORT_API_KEY=site_live_replace_with_the_one_time_value
```

**Rotate** creates a replacement and revokes the old key in the same database
transaction. There is no grace period: every client still using the old key
loses access immediately. **Revoke** also takes effect immediately. Revoked
records remain in `/admin/keys` as an audit trail and cannot be reactivated.

## Lead notifications

The site emails the owner whenever the form is submitted. Production Compose
requires `MAIL_MAILER` to be set so a deployment cannot silently ship with
log-only delivery. Use a real transactional mail service before launch. The
admin still warns if an operator explicitly selects a non-delivering mailer.

### Choosing where mail sends from

These notifications go **to the business owner**, not to their customers. That
matters, because it means the sending address does not have to be on the
client's domain — the owner only has to receive it, and the message already
sets Reply-To to the customer so replying reaches them.

So there are two workable arrangements:

**One agency sender for every client site.** Verify a subdomain you control
once, such as `mail.youragency.com`, at a transactional provider, and reuse the
credentials across every site. Each site sets `MAIL_FROM_NAME` to the client's
business name, so the owner sees `Copper Eagle <notifications@mail.youragency.com>`
in their inbox. One DNS setup, one account, nothing to do per client.

**A sender on the client's own domain.** Verify each client domain separately
and send as `site@theirdomain.com`. More branded and more work: DNS records per
client, and you are dependent on whoever controls their domain.

Start with the first. Move a client to the second when you add anything the
*customer* receives — an autoresponder confirming their enquiry — because that
genuinely should come from the business they contacted.

### Amazon SES, one domain for every client site

One verified domain sends for every site. Do this once.

**1. Verify the sending domain.** In the SES console, in the region you will
send from, create a domain identity for a subdomain you control — a subdomain
such as `mail.youragency.com` rather than the apex, so this cannot affect
ordinary mail on the parent domain. Enable Easy DKIM and add the three CNAME
records SES gives you. Verification completes within the hour once DNS
propagates.

Add a DMARC record on the sending domain too:

```text
_dmarc.mail.youragency.com  TXT  "v=DMARC1; p=none; rua=mailto:dmarc@youragency.com"
```

**2. Leave the sandbox.** New SES accounts can only send to addresses you have
individually verified, capped at 200 messages a day. That is fine for your own
testing and useless for real client owners. Request production access from the
SES console before launch — approval usually takes a day, and the request asks
what you send and how you handle bounces. Answer honestly: transactional lead
notifications to business owners, low volume, no marketing.

This is the step that catches people out. In the sandbox everything looks
configured, the test send to your own verified address arrives, and then the
first real client owner never receives anything.

**3. Create SMTP credentials.** In SES, choose **SMTP settings** and create
them there. These are *not* your AWS access keys — SES derives a separate SMTP
username and password, shown once. Store them in Dokploy, nowhere else.

**4. Note the regional endpoint.** It is region-specific and must match where
the domain is verified, for example `email-smtp.us-east-1.amazonaws.com`.

### Variables

Every client site gets the same four credentials and its own `MAIL_FROM_NAME`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your-ses-smtp-username
MAIL_PASSWORD=your-ses-smtp-password
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=notifications@mail.youragency.com
MAIL_FROM_NAME="${APP_NAME}"
```

Production startup is fail-closed: it rejects non-delivering mailers such as
`log` and `array`, and it exits before serving traffic when any SMTP transport
credential or sender field is blank.

`MAIL_FROM_NAME` carries the client's business name, so the owner sees
`Copper Eagle <notifications@mail.youragency.com>` rather than your agency name.
`MAIL_FROM_ADDRESS` must stay on the verified domain — SES rejects anything
else outright.

Port 587 with the `smtp` transport uses STARTTLS rather than port 25, which many
hosts block outbound. Use `smtps` only with an implicit-TLS endpoint. If 587 is
also blocked, SES accepts 2587.

### What the site owner can and cannot do

The SMTP host, credentials, and sender address come from the environment and
are never written to the settings table. Nothing in the admin changes them, so
a site owner — or anyone who gets hold of an owner's password — cannot point
the site at a different mail server or send as a different address. A test
asserts those keys never appear in settings.

What an owner *can* do is choose who gets notified and press a test button, and
because sending goes through credentials shared by every site, that is worth
bounding rather than trusting:

- at most five notification recipients
- three test sends per ten minutes
- the message body is fixed; only lead data fills it, never free text from the admin

The residual risk is small and worth stating plainly: an owner can send a
handful of fixed-content notifications to addresses of their choosing. They
cannot compose a message, cannot reach an arbitrary recipient list, and cannot
send from anything other than the verified domain.

If a client ever needs to send genuine volume, give them their own SES identity
rather than raising these limits.

### Watch the bounce rate

SES suspends accounts that exceed roughly 5% bounces or 0.1% complaints, and
because one account sends for every client site, one site's bad recipient
address affects all of them. At the volume a lead form produces this is
unlikely, but the shared account is the reason to care: check the SES
reputation dashboard occasionally, and fix a notification address that starts
bouncing rather than leaving it.

### Confirm it

From the Dokploy terminal for the running container:

```bash
php artisan mail:test you@youragency.com
```

It reports the sender and transport in use, then either accepts or prints the
provider's own error — which is what names a wrong region endpoint, credentials
that are an IAM key rather than SES SMTP credentials, or a sender on an
unverified domain.

Accepted is not the same as arrived. Check the inbox and the spam folder, then
send a real one through **Admin → Lead delivery**.

Recipients are set on that screen, one per line, defaulting to the contact
address from Site details. The red warning there disappears once mail is
genuinely configured, and names the specific problem while it is not.

## Deployment verification

Dokploy should report the container healthy. The startup log must show either
completed migrations or `Nothing to migrate`, followed by Laravel optimization
and FrankenPHP startup.

Read the startup log from Dokploy's **Logs** tab. For local operator checks with
the same Compose contract, use:

```bash
docker compose -f compose.production.yaml logs --tail=200 app
```

Verify these URLs after attaching the domain:

```text
https://your-production-domain.example/
https://your-production-domain.example/up
https://your-production-domain.example/robots.txt
https://your-production-domain.example/llms.txt
https://your-production-domain.example/sitemap.xml
```

Each URL must return HTTP 200. Inspect the homepage at desktop and mobile widths
before directing public traffic to the deployment.

Pasteable HTTP checks:

```bash
curl --fail --silent --show-error --output /dev/null https://your-production-domain.example/
curl --fail --silent --show-error --output /dev/null https://your-production-domain.example/up
curl --fail --silent --show-error --output /dev/null https://your-production-domain.example/robots.txt
curl --fail --silent --show-error --output /dev/null https://your-production-domain.example/llms.txt
curl --fail --silent --show-error --output /dev/null https://your-production-domain.example/sitemap.xml
```

Configure scheduled backups for both named volumes from Dokploy's volume backup
controls before storing customer or operational data. Complete one restore into
a throwaway Compose service and confirm `/up` plus `php artisan migrate:status`
before treating the backup policy as proven.

## Rollback

Redeploy the previous known-good Git commit from Dokploy's deployment history.
The application image is immutable, but database migrations are not
automatically reversed during an image rollback. Restore the matching volume
backup if a later migration made an incompatible data change.
