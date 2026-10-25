# Pricing

Reusable pricing-card sections. These templates define presentation only and
must never contain client prices or package claims.

## Variants

| Variant | Use |
|---|---|
| `monthly` | One monthly price per plan; no selector |
| `yearly` | One yearly price per plan; no selector |
| `toggle` | Accessible Monthly/Yearly selector |
| `factors` | Explains quote inputs without publishing or inventing prices |

All variants accept `id`, `eyebrow`, `heading`, `body`, and `plans`. Single-mode
variants also accept `badge`; `toggle` accepts `defaultMode` and `modes`.

Each plan accepts `name`, `summary`, optional `media`, `prices`, optional
`featureHeading`, `features`, optional `cta`, and `featured`.

The shared price shape is:

```php
'prices' => [
    'monthly' => ['amount' => '$0', 'suffix' => '/ month', 'note' => null],
    'yearly' => ['amount' => '$0', 'suffix' => '/ year', 'note' => null],
],
```

The values above explain the shape; they are not public content. Use real,
approved pricing only from the page or content record that invokes the block.

## Plan example

```php
[
    'name' => 'Plan name',
    'summary' => 'A short plan description.',
    'media' => [
        'src' => 'images/example.webp',
        'alt' => 'Descriptive alternative text',
        'width' => 128,
        'height' => 128,
    ],
    'prices' => [
        'monthly' => ['amount' => '$0', 'suffix' => '/ month', 'note' => null],
        'yearly' => ['amount' => '$0', 'suffix' => '/ year', 'note' => null],
    ],
    'featureHeading' => 'Included',
    'features' => ['First feature', 'Second feature'],
    'cta' => ['label' => 'Choose plan', 'href' => '#quote'],
    'featured' => false,
]
```

`media`, `featureHeading`, `features`, and `cta` are optional. Single-mode
variants omit plans that do not provide their selected price. The `toggle`
variant requires both prices for every plan and throws a clear validation
exception when its contract is incomplete.

## Factors contract

`factors` accepts `id`, optional `eyebrow`, `heading`, optional `body` (a short
white introduction), optional governed `image` (a decorative photo behind the
deep-blue band, under an 80% overlay; plain deep blue without it), and
`factors`. It renders as the "What to expect" band: copper H2, one column per
factor (up to four per row) under a small copper diamond. Each factor requires
a non-empty `label` and `description`:

```php
['label' => 'System size', 'description' => 'Explain the observed input.']
```

Invalid rows are omitted. Do not add price ranges, minimums, discounts, or
outcome claims unless they come from an approved content record.
