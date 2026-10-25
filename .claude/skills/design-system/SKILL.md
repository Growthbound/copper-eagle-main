---
name: design-system
description: The Copper Eagle visual system. Load this BEFORE changing anything a visitor sees — blocks, page templates, stylesheets, images, colors, fonts, spacing, layout — or before composing a page from blocks or choosing a page template. Do not load it for back-end code, tests unrelated to rendering, or copywriting (use brand-voice for words).
---

# Copper Eagle design system

**Read `docs/brand/design-system.md` before any visual change.** That file is
the single source of truth; this skill exists so it gets loaded at the right
moment.

Non-negotiables, in brief (the file has the detail and the reasons):

- Colors, fonts, radii, and the copper gradient come only from
  `resources/css/theme.css`. No hex, `rgb()`, Tailwind palette classes, other
  fonts, or literal `border-radius` values anywhere else.
- Outfit only; headings weight 400; uppercase letter-spaced labels; square
  corners except the listed circles.
- Pages use the fixed templates (`home`, `services-hub`, `service-detail`,
  `emergency`, `about`, `team`, `reviews`, `contact`, plus `decision-guide`
  for articles). Fill props; never add, drop, or reorder sections.
- A new look means a code change that updates the block, its README, the
  spec, the `/templates` gallery preview, and the tests together.

Check the result in the local gallery at `/templates` at 1440px, 768px, and
390px, and run `tests/Unit/DesignSystemContractTest.php` and
`tests/Feature/CopperEaglePageTemplatesTest.php`.
