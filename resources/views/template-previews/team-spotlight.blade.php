{{--
    The footer team spotlight with fictional people. On real pages it reads
    the published team page; staff photos never appear in a preview.
--}}
<x-layouts.marketing title="Team spotlight" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="team-spotlight-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="team-spotlight-preview-title">Team spotlight</h1>
        <p>Site chrome above the footer on every page except the team page. Each person's photo sits beside their name and bio on a copper panel over the drain photo; phones stack the photo over the panel.</p>
    </section>

    <x-team-spotlight
        heading="Meet the sample team"
        :members="[
            ['name' => 'Jordan Example', 'role' => 'Sample role', 'bio' => 'Fictional bio: answers the first call and keeps every visit on schedule. A second sentence shows how a longer bio wraps inside the copper panel.', 'image' => ['src' => '/images/home/technician-controls.jpg', 'alt' => 'Fictional team member', 'width' => 1600, 'height' => 2844]],
            ['name' => 'Taylor Example', 'role' => 'Sample role', 'bio' => 'Fictional bio: a short one.', 'image' => ['src' => '/images/home/technician-detail.jpg', 'alt' => 'Fictional team member', 'width' => 1600, 'height' => 2845]],
            ['name' => 'Casey Example', 'role' => 'Sample role', 'bio' => 'Fictional bio for the third sample person in this preview.', 'image' => ['src' => '/images/home/technician-maintenance.jpg', 'alt' => 'Fictional team member', 'width' => 1800, 'height' => 3200]],
        ]"
    />
</x-layouts.marketing>
