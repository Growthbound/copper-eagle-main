<x-layouts.marketing title="Team grid template" :noindex="true">
    <x-blocks.team.grid
        id="team-grid-preview"
        eyebrow="Fictional team"
        heading="Meet Our Indoor Air Team"
        body="Square photos, the name in ink, the role as an uppercase label, and an optional short bio."
        :members="[
            [
                'name' => 'Alex Sample',
                'role' => 'Service Coordinator',
                'bio' => 'Fictional bio: answers the first call and keeps every visit on schedule.',
                'image' => [
                    'src' => 'images/home/family-relaxing.jpg',
                    'alt' => 'Synthetic stock portrait for the team grid template',
                    'width' => 1600,
                    'height' => 2400,
                ],
                'linkedin_url' => 'https://www.linkedin.com/',
            ],
            [
                'name' => 'Jordan Sample',
                'role' => 'Indoor Air Specialist',
                'image' => [
                    'src' => 'images/home/technician-controls.jpg',
                    'alt' => 'Synthetic stock service image for the team grid template',
                    'width' => 1600,
                    'height' => 2844,
                ],
                'linkedin_url' => 'https://www.linkedin.com/',
            ],
            [
                'name' => 'Morgan Sample',
                'role' => 'Lead Technician',
                'bio' => 'Fictional bio: a longer line that shows how two or three sentences wrap under the role label without pushing the next card out of line.',
                'image' => [
                    'src' => 'images/home/family-living-room.jpg',
                    'alt' => 'Synthetic stock portrait for the team grid template',
                    'width' => 1800,
                    'height' => 2700,
                ],
                'linkedin_url' => 'https://www.linkedin.com/',
            ],
            [
                'name' => 'Taylor Sample',
                'role' => 'Customer Care',
                'image' => [
                    'src' => 'images/home/technician-detail.jpg',
                    'alt' => 'Synthetic stock service image for the team grid template',
                    'width' => 1600,
                    'height' => 2845,
                ],
                'linkedin_url' => 'https://www.linkedin.com/',
            ],
        ]"
    />
</x-layouts.marketing>
