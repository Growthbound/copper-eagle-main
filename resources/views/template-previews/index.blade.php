<x-layouts.marketing title="Template library" :noindex="true">
    <section class="template-gallery section-space" data-template-gallery>
        <header class="template-gallery-header">
            <p class="eyebrow text-accent">Non-production workspace</p>
            <h1>Template library</h1>
            <p>Open any template, then resize the browser or use device tools to review its desktop, tablet, and mobile behavior.</p>
        </header>

        <div class="template-gallery-summary" aria-label="Template library summary">
            <span>{{ count($templates) }} templates</span>
            <span>{{ $environment }} environment</span>
        </div>

        @if ($templates !== [])
            <div class="template-gallery-grid">
                @foreach ($templates as $template)
                    <article class="template-gallery-card" data-template-slug="{{ $template['slug'] }}">
                        <span class="template-gallery-number" aria-hidden="true">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <h2>{{ $template['title'] }}</h2>
                        <code>/_templates/{{ $template['slug'] }}</code>
                        <a href="{{ $template['href'] }}">
                            Open responsive preview <span aria-hidden="true">→</span>
                        </a>
                    </article>
                @endforeach
            </div>
        @else
            <p class="template-gallery-empty">No template previews have been added yet.</p>
        @endif
    </section>
</x-layouts.marketing>
