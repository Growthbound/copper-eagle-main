# Process steps

Numbered "how it works" sequences. Reduces booking anxiety by showing what a
visit actually involves.

## Expected props

| Prop | Type | Required | Notes |
|---|---|---|---|
| `heading` | string | no | Section heading, renders as `<h2>` |
| `eyebrow` | string | no | Short label above the section heading |
| `body` | string | no | Optional supporting introduction |
| `steps` | array | no | Explicit step list; otherwise defaults to `content/process` |

## Variants

| Variant | Layout |
|---|---|
| `grid` | Numbered cards with the middle one offset. Defaults to `content/process`. |
| `icon-grid` | Centered, borderless icon-led steps. Defaults to `content/process`. |

## Notes

Three or four steps works; more than five reads as bureaucracy. Keep step
numbers as strings (`'01'`) so leading zeros survive.

The `icon-grid` variant expects:

```php
[
    'icon' => 'calendar-days',
    'title' => 'Choose your date',
    'body' => 'Pick the appointment window that works for your home.',
]
```

Choose `icon` from `config/marketing-icons.php`. Do not draw or paste a custom
SVG. The shared `<x-marketing-icon>` wrapper keeps every step in the same
Heroicons outline family and applies consistent accessibility behavior.
