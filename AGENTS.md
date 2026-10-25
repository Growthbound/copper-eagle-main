# Repository agent contract

## Purpose

This repository is a reusable Laravel platform for home-service marketing
websites. It is currently instantiated for Copper Eagle, but platform code must
remain portable to other home-service brands.

This file governs repository operations, architecture, content safety, testing,
and delivery. Site-specific skills own brand voice, visual direction, offers,
editorial strategy, and campaign rules. For this instance that is
`docs/brand/voice.md`, which is binding on every word a customer reads. Do not
promote one site's brand guidance into shared platform behavior unless the owner
explicitly asks for a reusable platform feature.

These instructions apply to the entire repository. A more specific `AGENTS.md`
inside a subdirectory may add or narrow rules for that subtree.

## Start every task here

1. Confirm the authoritative worktree, branch, HEAD, upstream, and `git status`.
2. Preserve existing and untracked work. Never discard, overwrite, or stage
   unrelated files.
3. Read the relevant implementation and tests before editing.
4. Classify the request:
   - Code, templates, migrations, infrastructure, or reusable design-system
     changes move through Git branches and pull requests.
   - Managed page content uses the owner CMS or versioned content API.
   - Brand voice and page-design decisions come from the active site's skills,
     not this file. Writing or editing customer-facing copy means reading
     `docs/brand/voice.md` first — see "Brand voice" below.
5. State any assumption that would change public URLs, claims, permissions,
   persistence, deployment, or production behavior.

## Source-of-truth map

- `app/Content/`: governed blocks, page templates, rich-content rules,
  validation, publication lifecycle, discovery, redirects, and media usage.
- `resources/views/components/blocks/`: reusable semantic Blade blocks.
- `resources/views/page-templates/`: governed page composition.
- `resources/css/app.css` and `config/theme.php`: shared theme tokens and
  responsive visual rules.
- `app/Livewire/Admin/`: owner-facing CMS and operational controls.
- `routes/api.php`: versioned content and media APIs.
- `content/pages/`: deterministic legacy/import seed content, not the normal
  post-adoption publishing surface.
- `docs/brand/voice.md`: the customer-facing voice for this site. Binding on
  every word a customer reads; silent on code and internal documentation.
- `docs/brand/design-system.md`: the visual system for this site — tokens,
  typography, shape, components, brand assets, and the fixed page templates.
  Binding on every visual change; see "Design system" below.
- `resources/css/theme.css`: the only place colors, fonts, radii, and the
  copper gradient are defined. `resources/css/brand/*.css` applies them.
- `docs/content/hermes-content-contract.md`: human-readable Hermes API contract.
- `docs/content/owner-cms-runbook.md`: owner publishing workflow.
- `docs/content/publication-recovery.md`: conflict and recovery procedures.
- `/api/content/v1/schema`: live machine-readable block, template, enum, and
  rich-content contract.
- `/templates`: local-only visual catalog; it must remain noindex and return 404
  in production.

When documentation and the running API differ, stop and reconcile them. Do not
guess around contract drift.

## Git and pull-request workflow

- Never commit directly to `main`.
- Start from the intended base branch and create a focused branch.
- Keep commits cohesive and reviewable. Do not mix unrelated cleanup into a
  feature or content change.
- Inspect the complete diff before committing. Confirm no credentials, local
  databases, generated secrets, or unrelated files are staged.
- Push only when authorized.
- Open a pull request only when authorized. Include:
  - outcome and affected surfaces;
  - migration or deployment implications;
  - exact test and browser evidence;
  - remaining risks or external blockers;
  - screenshots for visible changes.
- Never merge, deploy, publish content, rotate credentials, or modify production
  state unless the request explicitly authorizes that action.

## Code-change rules

- Follow existing Laravel, Livewire, Blade, Tailwind, and PHPUnit patterns.
- Use tests to establish the failing behavior before implementing a fix or
  feature.
- Use escaped semantic Blade for managed strings. Never render managed content
  with raw `{!! !!}` output.
- Use registered blocks and templates. Managed content cannot select PHP
  classes, Blade paths, CSS classes, JavaScript, SVG, or arbitrary components.
- Use semantic runtime theme tokens. Do not hardcode a site's color palette into
  reusable components.
- Use the configured icon library; do not invent mismatched inline SVG styles.
- Preserve one public H1 per page. A standard hero owns the H1 when present;
  otherwise the standard page renderer creates it from the page title. Blocks
  own H2, and rich-content headings must progress sequentially through H3-H6.
- Images require meaningful alt text unless decorative, plus intrinsic width
  and height. Video blocks require the schema's poster/transcript metadata.
- Keep full-width visual fields separate from their constrained inner content.
- Maintain keyboard access, visible focus, reduced-motion behavior, and
  responsive layouts without horizontal overflow.

## Managed-content workflow

Before creating or changing managed content:

1. Read `docs/content/hermes-content-contract.md`.
2. Fetch `/api/content/v1/schema`; never rely on remembered field shapes.
3. Search the media library before importing or duplicating an asset.
4. Create or save a new immutable draft revision.
5. Validate the candidate.
6. Issue and inspect a preview.
7. Publish or schedule only with explicit authority and the required key scope.
8. Read back the entry and public URL after a state change.

Every state-changing API request needs a unique `Idempotency-Key`. Writes to an
existing entry also need the current `If-Match` lock version. Send a stable
`X-Request-ID` for traceability.

Default automated-agent credentials should be limited to `content:read`,
`content:draft`, `content:preview`, and `media:read`. Give `content:publish`,
`content:schedule`, or `content:rollback` only when the owner has deliberately
authorized that capability. Rotation revokes the old key immediately.

Never edit immutable revisions or managed database rows directly. Rollback
creates and publishes a new revision; it does not rewrite history. Never persist
editorial changes only inside an ephemeral application container.

## Content and evidence boundaries

- Never fabricate reviews, staff, certifications, prices, service areas,
  project outcomes, health claims, statistics, awards, addresses, or media.
- Use verified site settings, managed records, supplied evidence, and the
  active site's skills as the source of truth.
- Do not create swapped-city doorway pages or imply a storefront where none
  exists.
- Missing evidence is a blocker for that claim, not permission to invent a
  placeholder.
- Fictional people, claims, projects, and prices are allowed only inside
  explicitly labeled local template previews.
- Keep platform examples neutral. Per-site skills control tone, positioning,
  persuasion framework, content depth, and design taste.

## Brand voice

Every word a customer reads is governed by `docs/brand/voice.md`. Read it before
writing or editing website copy, service pages, blog articles, FAQs, emails,
texts, ads, calls to action, page titles, or meta descriptions. It does not
apply to code, comments, commit messages, or internal documentation, which stay
plain and direct.

Copper Eagle's voice has not been written yet, and the file says so. Until it
is, keep customer-facing copy plain, specific, and limited to facts the owner
has confirmed. Do not borrow another brand's voice, offers, or claims, including
those of other sites built on this platform.

Legal and regulated text is carved out, and the carve-out is not stylistic. The
privacy policy, terms and conditions, cookie policy, and disclaimers must come
from the owner or a legal review. They are binding disclosures rather than
marketing, so never draft them from scratch or sweep them into a voice pass.
Improve their surrounding page structure if a task calls for it, and leave the
text alone unless the owner explicitly authorizes a rewrite.

Voice never overrides evidence. Good voice and an unevidenced claim can read
identically, so a claim the owner has not confirmed — a founding year, a
license or certification, a review, a health or energy claim, a service area,
a price — stays out no matter how natural it sounds. Where the voice wants a
detail the evidence does not support, write around it. Missing evidence blocks
the claim; it does not license a placeholder standing in for one, and the only
place a fictional detail may appear is an explicitly labeled local template
preview.

## Design system

Every visual change — a block, page template, stylesheet, image, or layout —
is governed by `docs/brand/design-system.md`. Read it before starting. The
short version:

- Tokens only. No hex values, `rgb()`, Tailwind palette classes, other fonts,
  or literal `border-radius` values in blocks or stylesheets.
- Outfit is the only font. Headings are weight 400; labels are uppercase with
  `0.16em` tracking. Corners are square except the circles the spec lists.
- Every public page uses a fixed page template. Content agents fill props;
  they never add, remove, or reorder sections, choose block types inside a
  fixed template, or pass classes.
- A new look is a code change: update the block, its README, the spec, the
  `/templates` gallery preview, and the tests in the same commit.

`tests/Unit/DesignSystemContractTest.php` and
`tests/Feature/CopperEaglePageTemplatesTest.php` compare the code with the
spec and fail when they drift apart.

## Secrets and external systems

- Never commit `.env`, API keys, passwords, cookies, tokens, private URLs,
  database exports, or customer data.
- Use placeholders in documentation and tests.
- Treat media imports, webhooks, email, analytics, external APIs, GitHub,
  Dokploy, and production databases as external state.
- Read-only diagnostics do not authorize writes. Sending, importing, deleting,
  rotating, deploying, or publishing requires the authority implied by the
  request.
- Do not prune Docker images, volumes, databases, or caches that may belong to
  another project without explicit approval.

## Database, Docker, and deployment

- Migrations must work on a fresh database and an existing-schema copy.
- Down migrations remove only structures introduced by their matching up
  migration.
- Production storage, uploaded media, and the database must live on persistent
  volumes.
- Keep local Docker and `Dockerfile.production`/Dokploy behavior distinct.
- A successful local test is not proof of a successful image build, deployment,
  migration, health check, or production readback. Report each state separately.

## Required verification

Run focused tests while developing. Before declaring a branch ready, run:

```bash
composer test
vendor/bin/pint --test
npm run build
php artisan content:check
git diff --check
```

For migrations, also prove fresh-database and existing-schema migration paths.
For public UI changes, inspect the affected pages at 1440px, 768px, and 390px.
Check horizontal overflow, image loading and dimensions, heading hierarchy,
keyboard/focus behavior, reduced motion, console errors, and production guards.

For content API changes, prove authentication, exact scopes, idempotent replay,
stale `If-Match` rejection, stable errors, audit attribution, pagination, draft
exclusion, preview, publication, scheduling, and rollback as applicable.

## Definition of done

A task is complete only when:

- the requested behavior exists on the intended branch;
- focused and proportionate full-branch verification passes;
- public content remains validated and publication-controlled;
- documentation and `/api/content/v1/schema` agree;
- the diff contains no secrets or unrelated changes;
- migrations and deployment implications are explicit;
- any blocked or unverified boundary is reported precisely;
- commit, push, pull request, merge, deployment, and publication status are
  stated separately.
