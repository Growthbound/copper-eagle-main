<?php

namespace App\Support;

use App\Content\Enums\PageTemplate;

/**
 * Sample section content for the Copper Eagle page templates.
 *
 * Used by the local template gallery and as the starting draft when an owner
 * creates a page from one of these templates. Everything here is fictional
 * and claim-free: no prices, licenses, guarantees as fact, review scores, or
 * real people. Photos are brand photos without staff, or the stock photos the
 * gallery already uses. A draft built from this content is held back from
 * publication by its `_scaffold` flag until the owner replaces it.
 *
 * Keys are template => section => props, and each section's props match the
 * block type fixed for it in PageTemplateRegistry.
 */
final class CopperEagleTemplateContent
{
    /**
     * @return array<string, mixed>|null
     */
    public function props(PageTemplate $template, string $section, string $title): ?array
    {
        $props = $this->all($title)[$template->value][$section] ?? null;

        return is_array($props) ? ['id' => $section, ...$props] : null;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function all(string $title): array
    {
        return [
            PageTemplate::Home->value => [
                'hero' => [
                    'heading' => $title,
                    'body' => 'Drains, water heaters, fixtures and remodels, handled by a team that explains the work before it starts and leaves the room the way it found it.',
                    'primaryAction' => ['label' => 'Contact us today', 'href' => '#help-prompt'],
                    'image' => $this->photo('drain.jpg', 'Water running into a sink drain', 1200, 800),
                ],
                'introduction' => [
                    'heading' => 'Finally, a plumber who explains the job first.',
                    'emphasis' => 'explains the job first',
                    'body' => 'Every visit starts with a look at the problem and a plain description of what we found, what we recommend, and what it involves. You decide before any work begins.',
                    'image' => $this->photo('valve.jpg', 'Close-up of a pressure gauge on a water line', 900, 900),
                    'imageShape' => 'circle',
                    'cta' => ['label' => 'Meet the team', 'href' => '#assurances'],
                ],
                'testimonials' => [
                    'heading' => 'What customers say',
                    'items' => [
                        ['quote' => 'They found the leak behind the vanity, showed us the photos, and had it fixed the same afternoon.', 'author' => 'Jordan Example', 'location' => 'Sample neighborhood'],
                        ['quote' => 'Clear about the options, careful in the house, and the kitchen was cleaner than before they came.', 'author' => 'Taylor Example', 'location' => 'Sample neighborhood'],
                    ],
                    'link' => ['label' => 'Read more testimonials', 'href' => '#testimonials'],
                ],
                'assurances' => [
                    'heading' => 'The ultimate peace of mind.',
                    'emphasis' => 'peace of mind.',
                    'layout' => 'side',
                    'items' => [
                        ['title' => 'Done right the first time.', 'body' => 'We confirm the cause before we fix anything, so the repair addresses the real problem.', 'image' => $this->photo('bath-sink.jpg', 'Modern bathroom sink with a wall-mounted faucet', 900, 900)],
                        ['title' => 'Clear, upfront options.', 'body' => 'You hear what the job involves and what it costs before the work starts.', 'image' => $this->photo('kitchen.jpg', 'Bright kitchen with copper pans above the counter', 900, 900)],
                        ['title' => 'Careful in your home.', 'body' => 'Floors covered, work area tidy, and everything put back when we leave.', 'image' => $this->photo('valve.jpg', 'Stainless steel valve and gauge on a water line', 900, 900)],
                    ],
                ],
                'service-areas' => [
                    'eyebrow' => 'Plumbing services for these sample areas',
                    'heading' => 'Where we work',
                    'areas' => $this->sampleAreas(),
                    'note' => 'And more…',
                ],
                'help-prompt' => [
                    'heading' => 'What can we help you with?',
                    'primaryAction' => ['label' => 'Reach out to us today', 'href' => '#hero'],
                    'buttonStyle' => 'sky',
                ],
            ],

            PageTemplate::ServicesHub->value => [
                'hero' => [
                    'eyebrow' => 'Sample city and surrounding areas',
                    'heading' => $title,
                    'image' => $this->photo('copper-pipe.jpg', 'Copper pipe joint against a dark background', 1200, 800),
                ],
                'services' => [
                    'heading' => 'What can we help you with?',
                ],
                'not-sure' => [
                    'heading' => 'Not sure what you need?',
                    'body' => 'Describe what you are seeing or hearing, and we will help you work out what is going on and what the next step is.',
                    'primaryAction' => ['label' => 'Talk to a plumber', 'href' => '#hero'],
                    'background' => 'mist',
                ],
                'reviews-teaser' => [
                    'heading' => 'Friendly plumbers, by every account.',
                    'body' => 'Read what homeowners say about how the visit went, from the first call to the final walkthrough.',
                    'image' => $this->stock('technician-controls.jpg', 'Fictional technician checking equipment controls', 1600, 2844),
                    'cta' => ['label' => 'Testimonials', 'href' => '#services'],
                ],
            ],

            PageTemplate::ServiceDetail->value => [
                'hero' => [
                    'eyebrow' => 'In sample city and surrounding areas',
                    'heading' => $title,
                    'image' => $this->photo('drain.jpg', 'Water running into a sink drain', 1200, 800),
                ],
                'introduction' => [
                    'heading' => 'Why choose us for this service?',
                    'body' => "A slow drain or a recurring clog is usually a sign of something further down the line.\n\nWe find where it starts, clear it properly, and tell you what will keep it from coming back.",
                    'checklist' => [
                        ['label' => 'A camera look', 'text' => 'When the cause is not obvious, we look before we guess.'],
                        ['label' => 'The right tool', 'text' => 'Chosen for the pipe, not for the fastest finish.'],
                        'A clean work area when we leave',
                    ],
                    'background' => 'mist',
                    'cta' => ['label' => 'Get in touch with us', 'href' => '#final-response'],
                ],
                'offerings' => [
                    'heading' => 'What this service covers',
                    'layout' => 'stacked',
                    'background' => 'stone',
                    'items' => [
                        ['title' => 'Cleaning', 'body' => 'Clearing buildup so water flows freely again.', 'image' => $this->photo('drain.jpg', 'Sink drain with running water', 1200, 800)],
                        ['title' => 'Blockage removal', 'body' => 'Finding and removing the clog at its source.', 'image' => $this->photo('bath-sink.jpg', 'Bathroom sink and faucet', 900, 900)],
                        ['title' => 'Pipe repair', 'body' => 'Repairing or replacing damaged sections of pipe.', 'image' => $this->photo('copper-pipe.jpg', 'Copper pipe joint', 1200, 800)],
                        ['title' => 'Maintenance', 'body' => 'Regular care that keeps small problems small.', 'image' => $this->photo('valve.jpg', 'Valve and gauge on a water line', 900, 900)],
                    ],
                ],
                'details' => [
                    'heading' => 'Why regular maintenance matters',
                    'body' => 'Small issues are cheaper to fix than big ones. A periodic check catches buildup, worn seals and slow leaks before they turn into water damage.',
                    'checklist' => ['Fewer emergency calls', 'Longer life for fixtures and pipes', 'Early warning of hidden leaks'],
                    'image' => $this->photo('kitchen.jpg', 'Kitchen counter with copper pans', 900, 900),
                    'imageSide' => 'end',
                ],
                'guarantee' => [
                    'eyebrow' => 'Sample promise eyebrow',
                    'heading' => 'Sample guarantee headline',
                    'body' => 'A sample promise about the work. Like the live guarantee band, it is a statement with no button.',
                    'tone' => 'copper',
                ],
                'faq' => [
                    'heading' => 'Common questions',
                    'items' => [
                        ['question' => 'How often should this be done?', 'answer' => "It depends on the home and how it is used.\n\nWe will suggest a schedule after looking at your system."],
                        ['question' => 'What warning signs should I watch for?', 'answer' => 'Slow draining, gurgling sounds, and odors near fixtures are the most common early signs.'],
                        ['question' => 'Do I need to be home for the visit?', 'answer' => 'Someone needs to let us in and agree on the work before we start.'],
                    ],
                ],
                'final-response' => [
                    'heading' => 'Book your service now.',
                    'emphasis' => 'Book your service',
                    'primaryAction' => ['label' => 'Get in touch with us', 'href' => '#hero'],
                ],
            ],

            PageTemplate::Emergency->value => [
                'hero' => [
                    'eyebrow' => 'After hours plumbing',
                    'heading' => $title,
                    'image' => $this->photo('copper-pipe.jpg', 'Copper pipe joint against a dark background', 1200, 800),
                ],
                'reassurance' => [
                    'heading' => 'Has something gone wrong in the middle of the night?',
                    'eyebrow' => 'We can help.',
                    'lead' => 'Call and we will talk you through the first steps.',
                    'body' => 'Describe what is happening. We will tell you what to do right away to limit the damage, and when we can be there.',
                    'cta' => ['label' => 'Call us now', 'href' => '#final-response'],
                ],
                'what-to-expect' => [
                    'heading' => 'What to expect',
                    'factors' => [
                        ['label' => 'After-hours visit', 'description' => 'How after-hours visits are priced is explained on the phone before anyone is sent.'],
                        ['label' => 'Diagnosis', 'description' => 'We find the cause and explain it before recommending any work.'],
                        ['label' => 'Extended diagnosis', 'description' => 'If the problem needs more tools or time to pin down, you hear that first.'],
                        ['label' => 'Upfront options', 'description' => 'You see the options and their cost before work starts.'],
                    ],
                ],
                'final-response' => [
                    'heading' => 'Let us help so you can get back to bed.',
                    'emphasis' => 'back to bed.',
                    'primaryAction' => ['label' => 'Call us now', 'href' => '#hero'],
                ],
            ],

            PageTemplate::About->value => [
                'hero' => [
                    'eyebrow' => 'Sample about eyebrow',
                    'heading' => $title,
                    'image' => $this->photo('copper-pipe.jpg', 'Copper pipe joint against a dark background', 1200, 800),
                ],
                'story' => [
                    'heading' => 'We strive for excellence on every job.',
                    'emphasis' => 'excellence',
                    'body' => 'Sample story copy: how the company started, what it cares about, and what a customer can expect from the first call to the final walkthrough.',
                    'image' => $this->stock('technician-maintenance.jpg', 'Fictional technician completing equipment maintenance', 1800, 3200),
                ],
                'values' => [
                    'heading' => 'What sets us apart',
                    'layout' => 'stacked',
                    'background' => 'stone',
                    'items' => [
                        ['title' => 'Communication', 'body' => 'You always know what we found and what happens next.', 'image' => $this->photo('kitchen.jpg', 'Kitchen counter with copper pans', 900, 900)],
                        ['title' => 'Competence', 'body' => 'The right fix the first time, done to code.', 'image' => $this->photo('valve.jpg', 'Valve and gauge on a water line', 900, 900)],
                        ['title' => 'Value', 'body' => 'Clear options, so you can choose what fits.', 'image' => $this->photo('bath-sink.jpg', 'Bathroom sink and faucet', 900, 900)],
                    ],
                ],
                'final-response' => [
                    'heading' => 'Let our team take care of your plumbing.',
                    'primaryAction' => ['label' => 'Get in touch with us', 'href' => '#hero'],
                ],
            ],

            PageTemplate::Team->value => [
                'hero' => [
                    'eyebrow' => 'Sample team eyebrow',
                    'heading' => $title,
                ],
                'team' => [
                    'heading' => 'Meet the team',
                    'body' => 'Sample introduction to the people who come to your home.',
                    'members' => [
                        ['name' => 'Jordan Example', 'role' => 'Sample role', 'bio' => 'Fictional team member used only in this preview.', 'image' => $this->stock('technician-controls.jpg', 'Fictional team member', 1600, 2844)],
                        ['name' => 'Taylor Example', 'role' => 'Sample role', 'bio' => 'Fictional team member used only in this preview.', 'image' => $this->stock('technician-detail.jpg', 'Fictional team member', 1600, 2845)],
                        ['name' => 'Casey Example', 'role' => 'Sample role', 'bio' => 'Fictional team member used only in this preview.', 'image' => $this->stock('technician-maintenance.jpg', 'Fictional team member', 1800, 3200)],
                    ],
                ],
                'final-response' => [
                    'heading' => 'Want to work with us?',
                    'primaryAction' => ['label' => 'Get in touch with us', 'href' => '#hero'],
                ],
            ],

            PageTemplate::Reviews->value => [
                'hero' => [
                    'eyebrow' => 'Testimonials and',
                    'heading' => $title,
                ],
                'testimonials' => [
                    'heading' => 'What customers say',
                    'items' => [
                        ['quote' => 'Friendly, on time, and explained everything before starting.', 'author' => 'Jordan Example'],
                        ['quote' => 'Fixed the water heater the same day and cleaned up after.', 'author' => 'Taylor Example'],
                        ['quote' => 'Called back to make sure everything was still working.', 'author' => 'Casey Example'],
                    ],
                ],
                'leave-a-review' => [
                    'heading' => 'Your review is next!',
                    'cta' => ['label' => 'Leave us a review', 'href' => '#testimonials'],
                ],
                'final-response' => [
                    'heading' => 'Let us help with your home’s plumbing.',
                    'primaryAction' => ['label' => 'Get in touch with us', 'href' => '#hero'],
                ],
            ],

            PageTemplate::Contact->value => [
                'hero' => [
                    'eyebrow' => 'Sample contact eyebrow',
                    'heading' => $title,
                    'image' => $this->photo('drain.jpg', 'Water running into a sink drain', 1200, 800),
                ],
                'message' => [
                    'heading' => 'Send us a message',
                    'body' => 'Tell us what is going on and the best way to reach you. We will get back to you to talk it through.',
                    'image' => $this->photo('bath-sink.jpg', 'Bathroom sink and faucet', 900, 900),
                    'secondaryImage' => $this->photo('copper-pipe.jpg', 'Copper pipe joint', 1200, 800),
                    'showContact' => true,
                    'submitLabel' => 'Send message',
                    'showMessage' => true,
                ],
                'service-areas' => [
                    'eyebrow' => 'Serving these sample areas',
                    'heading' => 'Where we work',
                    'areas' => $this->sampleAreas(),
                ],
                'emergency' => [
                    'eyebrow' => 'Sample emergency eyebrow',
                    'heading' => 'Plumbing emergencies',
                    'cta' => ['label' => 'Learn more', 'href' => '#hero'],
                ],
                'social' => [
                    'heading' => 'Connect with us on social media',
                    'profiles' => [
                        ['label' => 'Instagram', 'handle' => '@sample.company', 'href' => 'https://example.com/sample-profile'],
                        ['label' => 'YouTube', 'handle' => '@SampleCompany'],
                    ],
                    'posts' => [
                        ['href' => 'https://example.com/sample-post-1', 'image' => $this->photo('drain.jpg', 'Water running into a sink drain', 1200, 800)],
                        ['href' => 'https://example.com/sample-post-2', 'image' => $this->photo('bath-sink.jpg', 'Bathroom sink and faucet', 900, 900)],
                        ['href' => 'https://example.com/sample-post-3', 'image' => $this->photo('copper-pipe.jpg', 'Copper pipe joint', 1200, 800)],
                        ['href' => 'https://example.com/sample-post-4', 'image' => $this->photo('valve.jpg', 'Valve and gauge on a water line', 900, 900)],
                        ['href' => 'https://example.com/sample-post-5', 'image' => $this->photo('kitchen.jpg', 'Kitchen counter with copper pans', 900, 900)],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, href: string}>
     */
    private function sampleAreas(): array
    {
        return collect(['Springfield', 'Riverton', 'Lakeside', 'Hillcrest', 'Fairview', 'Oak Park'])
            ->map(fn (string $name): array => ['name' => $name, 'href' => '/areas'])
            ->all();
    }

    /**
     * @return array{src: string, alt: string, width: int, height: int}
     */
    private function photo(string $file, string $alt, int $width, int $height): array
    {
        return ['src' => '/images/brand/photos/'.$file, 'alt' => $alt, 'width' => $width, 'height' => $height];
    }

    /**
     * @return array{src: string, alt: string, width: int, height: int}
     */
    private function stock(string $file, string $alt, int $width, int $height): array
    {
        return ['src' => '/images/home/'.$file, 'alt' => $alt, 'width' => $width, 'height' => $height];
    }
}
