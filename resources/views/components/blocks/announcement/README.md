# Announcement

Site-wide promo or notice bar. Unlike every other block, this one is not placed
on a page by hand — the marketing layout renders it automatically and the owner
controls it from **Admin → Announcement**.

## How it is chosen

The admin screen lists every `.blade.php` file in this folder as a selectable
style. Adding a variant here makes it available in that dropdown on the next
page load; there is no registry to update.

The bar renders only when all of these are true:

- the announcement is enabled
- the message is not blank
- now is at or after the start time, if one is set
- now is at or before the end time, if one is set

Leaving both times blank runs it indefinitely. Setting only an end time starts
it immediately and retires it automatically — the common case for a seasonal
promo nobody wants to remember to turn off.

## Props every variant receives

| Prop | Type | Notes |
|---|---|---|
| `message` | string | The notice text. Always present |
| `url` | string\|null | Optional destination |
| `linkLabel` | string\|null | Link text. Falls back to "Learn more" when a URL is set |

## Variants

| Variant | Layout |
|---|---|
| `bar` | Full-width strip above the header. |

## Notes

Keep it to one short sentence — this sits above the header and eats vertical
space on mobile.

The bar is part of the page, not fixed to the viewport, so it scrolls away and
never covers content. If you build a fixed variant, add matching top padding to
the page or it will overlap the header.
