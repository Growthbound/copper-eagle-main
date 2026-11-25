# Home plumbing quiz

The fixed flow at `/home-plumbing-quiz` has an introduction, six questions, and a
final report delivery form for required contact details and an optional free
home plumbing inspection. The confirmation page includes the report and a PDF
download. Browser sessions keep progress and authorize the download. The beta
start page is listed in `/sitemap.xml` and stays off the site menus. The steps
and report use `noindex`, as does the start page on noncanonical hosts.
The gallery version at `/_templates/home-plumbing-quiz` uses its own session
state, saves no leads, and returns 404 in production.

The migrations add a unique submission ID, answers, inspection choice, and PDF
email delivery state to `leads`. Submitting the form saves contact details and
answers under **Admin → Leads**. Inspection requests appear under **New** and
use the site's configured lead notification email. The office confirms an
appointment by phone. Other submissions appear under **Archived**. Quiz
submissions do not dispatch website lead webhooks or HighLevel automations.

Each submitted quiz uses the configured Laravel mailer to send the visitor a PDF
attachment. Dompdf uses the bundled Outfit TrueType copies derived from this
site's Fontsource WOFF files. PDFs are generated in memory. The app attempts
delivery after the form response. The existing Laravel scheduler runs
`quiz:send-reports` every minute to recover unsent reports, with at most three
attempts per report and five minutes between attempts. A report can always be
downloaded from its completed quiz session. On a deployment using the `log`,
`array`, or `null` default mailer, report delivery stays pending.

The Microsoft Clarity SDK uses **Admin → Tracking → Microsoft Clarity** or
`TRACKING_CLARITY_PROJECT_ID` and respects the existing host rules. When
configured, it loads on the introduction and question screens and masks answer
controls. It is absent on contact, results, confirmation, and preview screens.
