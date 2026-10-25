# Site Footer Templates

Selectable site-chrome templates for page-building agents.

## Structured

```blade
<x-site-footers.structured
    :company-links="$companyLinks"
    :resource-links="$resourceLinks"
/>
```

The structured variant follows a four-region desktop hierarchy:

1. Brand and site description.
2. Company navigation.
3. Resource navigation.
4. Contact details.

Social profiles and copyright sit in a lower strip. The layout collapses to
two columns and then one column as its own container narrows.

## Props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Defaults to `structured-footer` |
| `companyLinks` | array | no | Rows contain `label` and `href`; defaults to `config('site.navigation')` |
| `resourceLinks` | array | no | Rows contain `label` and `href`; defaults to services, areas, `llms.txt`, and sitemap |
| `companyHeading` | string | no | Accessible heading and navigation label |
| `resourceHeading` | string | no | Accessible heading and navigation label |
| `contactHeading` | string | no | Heading for verified business details |
| `socialHeading` | string | no | Accessible social-navigation label |

Blank or malformed link rows are removed. Link destinations must be relative,
fragment-only, or use HTTP/HTTPS.

## Data ownership

Business name, description, address, email, phone, and social profiles come
from `config('site')`. The owner-editable settings layer overrides those values
at runtime. Do not copy contact values from a template preview into public
configuration.

Blank contact values are hidden. The component never substitutes placeholder
claims. Address, email, and phone use the approved `map-pin`, `envelope`, and
`phone` icons from the marketing icon catalog.

## Previewing

The local-only gallery is available at `/_templates/footer`. It disables the
global footer so only the selected template appears. The preview is noindex,
absent from discovery files, and unavailable in production.
