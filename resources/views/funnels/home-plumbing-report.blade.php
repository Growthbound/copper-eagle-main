<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your home plumbing report</title>
    <style>
        @font-face { font-family: 'Outfit'; src: url('file://{{ $fonts }}/Outfit-Regular.ttf') format('truetype'); font-weight: 400; }
        @font-face { font-family: 'Outfit'; src: url('file://{{ $fonts }}/Outfit-SemiBold.ttf') format('truetype'); font-weight: 600; }
        @page { margin: 25pt 42pt; }
        body { margin: 0; color: {{ $palette['ink'] }}; font-family: Outfit; font-size: 10.5pt; line-height: 1.23; }
        p, h1, h2 { margin: 0; }
        .logo { display: block; width: 146pt; height: auto; margin-bottom: 10pt; }
        .hero { padding: 11pt 18pt 12pt; background: {{ $palette['teal'] }}; color: {{ $palette['white'] }}; }
        .label { font-size: 8pt; letter-spacing: 0.16em; text-transform: uppercase; }
        h1 { font-size: 26pt; line-height: 1.1; font-weight: 400; margin: 4pt 0 6pt; }
        h2 { font-size: 15pt; line-height: 1.15; font-weight: 400; color: {{ $palette['teal'] }}; margin: 2pt 0 3pt; }
        .check { background: {{ $palette['mist'] }}; padding: 6pt 14pt; margin-top: 7pt; page-break-inside: avoid; }
        .check .label { color: {{ $palette['orange-ink'] }}; }
        .check p + p { margin-top: 2pt; }
        strong { font-weight: 600; }
        footer { margin-top: 9pt; padding-top: 6pt; border-top: 1pt solid {{ $palette['teal'] }}; font-size: 8.5pt; }
        footer a { color: {{ $palette['teal'] }}; text-decoration: underline; }
        footer a + a { margin-left: 10pt; }
    </style>
</head>
<body>
    @if (is_file($logo))
        <img class="logo" src="file://{{ $logo }}" alt="{{ config('site.name') }}" width="1200" height="245">
    @endif
    <header class="hero">
        <p class="label">3-5 year planning checklist</p>
        <h1>Your home plumbing report</h1>
        <p>Here’s what to check now and what to plan for based on your quiz answers.</p>
    </header>

    <main>
        @foreach ($checklist as $item)
            <section class="check">
                <p class="label">{{ $item['priority'] }}</p>
                <h2>{{ $item['title'] }}</h2>
                <p>{{ str_replace('–', '-', $item['reason']) }}</p>
                <p><strong>What to check.</strong> {{ $item['check'] }}</p>
            </section>
        @endforeach
    </main>

    <footer>
        <p class="label">Sources</p>
        @foreach ($sources as $label => $href)
            <a href="{{ $href }}">{{ $label }}</a>
        @endforeach
    </footer>
</body>
</html>
