# Team

Team portraits and role summaries.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Section ID; defaults to `team-grid` |
| `eyebrow` | string | no | Short context above the heading |
| `heading` | string | yes | Renders as the section `<h2>` |
| `body` | string | no | Short supporting introduction |
| `members` | array | no | Team member records described below |

Each member record accepts:

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | Public display name |
| `role` | string | yes | Public job title or responsibility |
| `image` | array | yes | Requires `src`, `alt`, positive `width`, and positive `height` |
| `linkedin_url` | string | no | Must be an absolute HTTP or HTTPS URL |

Incomplete member records are omitted. Invalid or unsafe profile URLs are
omitted without removing an otherwise valid member.

## Variants

| Variant | Layout |
|---|---|
| `grid` | Four portraits on desktop, two on tablet, and one on mobile. |

## Usage

```blade
<x-blocks.team.grid
    heading="Meet the team"
    :members="[
        [
            'name' => 'Example Person',
            'role' => 'Service Manager',
            'image' => [
                'src' => 'images/team/example.jpg',
                'alt' => 'Example Person, service manager',
                'width' => 1200,
                'height' => 1500,
            ],
        ],
    ]"
/>
```

Use approved, current staff photography and titles on public pages. The local
preview intentionally uses synthetic people and stock images.
