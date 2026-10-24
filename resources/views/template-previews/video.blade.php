<x-layouts.marketing title="Video block" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Video block</h1>
        <p>A native video specimen with poster, caption, controls, and transcript.</p>
    </section>
    <x-blocks.media.video
        heading="Watch a fictional service walkthrough."
        :video="['src' => 'https://example.com/fictional-service.mp4', 'type' => 'video/mp4']"
        :poster="['src' => '/images/home/technician-maintenance.jpg', 'alt' => 'Fictional technician shown in the video poster', 'width' => 1800, 'height' => 3200]"
        caption="Fictional training video used only to demonstrate the component."
        transcript="Fictional transcript: the technician explains the proposed scope, prepares the work area, completes the agreed service, and reviews the documented work with the homeowner."
    />
</x-layouts.marketing>
