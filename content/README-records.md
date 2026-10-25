# Records

Content that appears in more than one place lives here as JSON files, one per
record, named by slug. A record is written once and read everywhere it belongs.

| Directory | Produces |
|---|---|
| `services/` | Cards, the enquiry dropdown, a page each, a page per area, sitemap entries, `Service` markup |
| `areas/` | The footer list, a page each, a page per service, `areaServed` in the business markup |
| `faqs/` | The accordion on the homepage and on service and area pages, `FAQPage` markup |
| `testimonials/` | Quote cards, scopable to a service or an area |
| `benefits/` | The rotating benefit panel |
| `process/` | The numbered "how it works" steps |
| `resources/` | The homeowner-notes cards |

Each directory that needs explaining has its own README.

## What does not live here

`config/site.php` holds business identity only — name, description, phone,
email, address, social profiles, and the header menu — and most of it is
editable in the admin so the owner can change a phone number without a deploy.

The split is the point: an agent maintains the repository, an owner maintains
the admin, and there is exactly one answer to who edits what. A unit test
enforces it, so page content cannot quietly drift back into config.

## Before committing

```bash
php artisan content:check
```

Validates every page and record, and reports by filename: JSON that will not
parse, a missing required field, a block type with no template, a record scoped
to a service or area that does not exist, and titles or descriptions long
enough to be truncated in search results.
