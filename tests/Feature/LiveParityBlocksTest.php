<?php

namespace Tests\Feature;

use App\Content\BlockRegistry;
use App\Content\PublicLinkTarget;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The block props added to rebuild the live site exactly: background videos
 * in the heroes, a photo beside the closing prompt, a buttonless guarantee
 * band with an optional logo, the contact details and second photo beside the
 * contact form, the social feed and profile tiles, readable article dates,
 * and the footer team spotlight. Each falls back to the earlier look when
 * omitted and is part of the governed content contract.
 */
class LiveParityBlocksTest extends TestCase
{
    private const PHOTO = [
        'src' => '/images/brand/photos/copper-pipe.jpg',
        'alt' => 'Copper pipe joint',
        'width' => 1200,
        'height' => 800,
    ];

    private const VIDEO = [
        'src' => '/videos/brand/hero-home.mp4',
        'poster' => ['src' => '/videos/brand/hero-home-poster.jpg', 'width' => 1280, 'height' => 720],
    ];

    public function test_photo_hero_plays_a_decorative_background_video_over_its_photo(): void
    {
        $html = Blade::render(
            '<x-blocks.hero.photo heading="Plumbing help" :primary-action="$action" :image="$image" :video="$video" :service-strip="false" />',
            ['action' => ['label' => 'Contact us', 'href' => '#contact'], 'image' => self::PHOTO, 'video' => self::VIDEO],
        );

        $video = $this->tag($html, 'video');

        foreach (['autoplay', 'muted', 'loop', 'playsinline', 'preload="none"', 'aria-hidden="true"', 'data-background-video'] as $attribute) {
            $this->assertStringContainsString($attribute, $video, $attribute);
        }

        $this->assertStringContainsString('data-src="/videos/brand/hero-home.mp4"', $video);
        $this->assertStringContainsString('data-type="video/mp4"', $video);
        $this->assertStringContainsString('poster="/videos/brand/hero-home-poster.jpg"', $video);
        $this->assertStringNotContainsString('<source', $html, 'The video source is attached only when motion is allowed.');

        // The photo stays as the fallback and the priority image, under the video.
        $this->assertStringContainsString('class="photo-hero-image"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertLessThan(strpos($html, '<video'), strpos($html, 'class="photo-hero-image"'));

        // A pause control, revealed by the script once the video plays.
        $this->assertMatchesRegularExpression('/<button[^>]*data-background-video-control[^>]*hidden/', $html);
        $this->assertStringContainsString('aria-label="Pause background video"', $html);

        $plain = Blade::render(
            '<x-blocks.hero.photo heading="Plumbing help" :primary-action="$action" :image="$image" :service-strip="false" />',
            ['action' => ['label' => 'Contact us', 'href' => '#contact'], 'image' => self::PHOTO],
        );

        $this->assertStringNotContainsString('<video', $plain);
        $this->assertStringNotContainsString('data-background-video-control', $plain);

        $unsafe = Blade::render(
            '<x-blocks.hero.photo heading="Plumbing help" :primary-action="$action" :image="$image" :video="$video" :service-strip="false" />',
            ['action' => ['label' => 'Contact us', 'href' => '#contact'], 'image' => self::PHOTO, 'video' => [...self::VIDEO, 'src' => 'javascript:alert(1)']],
        );

        $this->assertStringNotContainsString('<video', $unsafe);
        $this->assertStringNotContainsString('javascript:', $unsafe);
    }

    public function test_interior_hero_plays_a_background_video_with_or_without_a_photo(): void
    {
        $html = Blade::render('<x-blocks.hero.interior eyebrow="Salt Lake City" heading="Services" :video="$video" />', ['video' => self::VIDEO]);

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('has-backdrop', $html, 'The deep-blue overlay keeps the H1 legible over the video.');
        $this->assertStringContainsString('class="interior-hero-video"', $html);
        $this->assertStringContainsString('poster="/videos/brand/hero-home-poster.jpg"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $this->tag($html, 'video'));

        $withPhoto = Blade::render('<x-blocks.hero.interior heading="Team" :image="$image" :video="$video" />', ['image' => self::PHOTO, 'video' => self::VIDEO]);

        $this->assertLessThan(strpos($withPhoto, '<video'), strpos($withPhoto, 'class="interior-hero-backdrop"'));
    }

    public function test_the_video_prop_is_part_of_both_hero_contracts(): void
    {
        $registry = app(BlockRegistry::class);
        $action = ['label' => 'Contact us', 'href' => '#contact'];

        $this->assertSame([], $registry->validate('hero/photo', ['heading' => 'H', 'primaryAction' => $action, 'image' => self::PHOTO, 'video' => self::VIDEO])->errors());
        $this->assertSame([], $registry->validate('hero/interior', ['heading' => 'H', 'video' => self::VIDEO])->errors());

        foreach (['hero/photo', 'hero/interior'] as $type) {
            $field = $registry->definition($type)->fields['video'];

            $this->assertFalse($field['required'], $type);
            $this->assertSame('object', $field['shape'], $type);
            $this->assertArrayHasKey('poster', $field['properties'], $type);
        }

        foreach ([
            'unsafe source' => [...self::VIDEO, 'src' => 'javascript:alert(1)'],
            'not a video file' => [...self::VIDEO, 'src' => '/videos/brand/hero-home.gif'],
            'no poster size' => [...self::VIDEO, 'poster' => ['src' => '/videos/brand/hero-home-poster.jpg']],
        ] as $case => $video) {
            $this->assertArrayHasKey('/props/video', $registry->validate('hero/interior', ['heading' => 'H', 'video' => $video])->errors(), $case);
            $this->assertArrayHasKey('/props/video', $registry->validate('hero/photo', ['heading' => 'H', 'primaryAction' => $action, 'image' => self::PHOTO, 'video' => $video])->errors(), $case);
        }
    }

    public function test_inline_band_takes_an_optional_photo_beside_the_prompt(): void
    {
        $html = Blade::render(
            '<x-blocks.cta.inline-band heading="Let us help you" :primary-action="$action" :image="$image" />',
            ['action' => ['label' => 'Get in touch', 'href' => '/contact'], 'image' => self::PHOTO],
        );

        $this->assertStringContainsString('has-media', $html);
        $this->assertStringContainsString('class="inline-cta-media"', $html);
        $this->assertStringContainsString('alt="Copper pipe joint"', $html);
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="800"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertSame(1, substr_count($html, '<h2'));

        $plain = Blade::render('<x-blocks.cta.inline-band heading="Let us help you" :primary-action="$action" />', ['action' => ['label' => 'Get in touch', 'href' => '/contact']]);

        $this->assertStringNotContainsString('has-media', $plain);
        $this->assertStringNotContainsString('<img', $plain);

        $registry = app(BlockRegistry::class);

        $this->assertSame([], $registry->validate('cta/inline-band', ['heading' => 'H', 'primaryAction' => ['label' => 'Go', 'href' => '#go'], 'image' => self::PHOTO])->errors());
        $this->assertArrayHasKey('/props/image', $registry->validate('cta/inline-band', ['heading' => 'H', 'primaryAction' => ['label' => 'Go', 'href' => '#go'], 'image' => [...self::PHOTO, 'alt' => '']])->errors());
    }

    public function test_cta_panel_button_is_optional_and_it_takes_a_small_logo(): void
    {
        $registry = app(BlockRegistry::class);

        $this->assertFalse($registry->definition('cta/panel')->fields['cta']['required']);
        $this->assertSame([], $registry->validate('cta/panel', ['heading' => '100% Guaranteed', 'tone' => 'copper'])->errors());
        $this->assertArrayHasKey('/props/cta', $registry->validate('cta/panel', ['heading' => 'H', 'cta' => ['label' => 'Bad', 'href' => 'javascript:alert(1)']])->errors());

        $statement = Blade::render('<x-blocks.cta.panel eyebrow="DONE RIGHT, EVERY TIME" heading="100% Guaranteed" body="Our promise." tone="copper" />');

        $this->assertStringContainsString('is-copper', $statement);
        $this->assertStringNotContainsString('class="button', $statement);
        $this->assertStringNotContainsString('cta-call', $statement, 'Without a button there is no "or call" alternative to offer.');

        $logo = ['src' => '/images/brand/pages/google-g-icon-download.png', 'alt' => 'Google logo', 'width' => 512, 'height' => 512];
        $review = Blade::render(
            '<x-blocks.cta.panel heading="Your review is next!" :cta="$cta" :logo="$logo" />',
            ['cta' => ['label' => 'LEAVE US A REVIEW', 'href' => 'https://example.com/review'], 'logo' => $logo],
        );

        $this->assertStringContainsString('class="cta-panel-logo"', $review);
        $this->assertStringContainsString('alt="Google logo"', $review);
        $this->assertStringContainsString('width="512"', $review);
        $this->assertLessThan(strpos($review, '<h2'), strpos($review, 'cta-panel-logo'));
        $this->assertSame([], $registry->validate('cta/panel', ['heading' => 'H', 'logo' => $logo])->errors());
        $this->assertArrayHasKey('/props/logo', $registry->validate('cta/panel', ['heading' => 'H', 'logo' => [...$logo, 'width' => 0]])->errors());
    }

    public function test_contact_form_split_shows_the_site_phone_and_email_and_a_second_photo(): void
    {
        config()->set('site.phone', '(555) 555-0100');
        config()->set('site.email', 'hello@example.test');

        $html = Blade::render(
            '<x-blocks.cta.quote-form-split heading="Call or Text" body="Give us a call today." :show-contact="true" :image="$image" :secondary-image="$second" />',
            ['image' => self::PHOTO, 'second' => ['src' => '/images/brand/photos/valve.jpg', 'alt' => 'Valve', 'width' => 900, 'height' => 900]],
        );

        $this->assertStringContainsString('href="tel:+15555550100"', $html);
        $this->assertStringContainsString('(555) 555-0100', $html);
        $this->assertStringContainsString('href="mailto:hello@example.test"', $html);
        $this->assertStringContainsString('class="contact-split-secondary"', $html);
        $this->assertStringContainsString('src="/images/brand/photos/valve.jpg"', $html);
        $this->assertLessThan(strpos($html, 'Give us a call today.'), strpos($html, 'tel:+15555550100'));
        $this->assertLessThan(strpos($html, 'mailto:'), strpos($html, 'Give us a call today.'));

        $hidden = Blade::render('<x-blocks.cta.quote-form-split heading="Send a message" />');

        $this->assertStringNotContainsString('mailto:', $hidden);
        $this->assertStringNotContainsString('contact-split-call', $hidden);

        config()->set('site.email', '');
        $noEmail = Blade::render('<x-blocks.cta.quote-form-split heading="Call or Text" :show-contact="true" />');

        $this->assertStringNotContainsString('mailto:', $noEmail);

        $registry = app(BlockRegistry::class);

        $this->assertSame('boolean', $registry->definition('cta/quote-form-split')->fields['showContact']['type']);
        $this->assertSame([], $registry->validate('cta/quote-form-split', ['heading' => 'H', 'showContact' => true, 'secondaryImage' => self::PHOTO])->errors());
        $this->assertArrayHasKey('/props/secondaryImage', $registry->validate('cta/quote-form-split', ['heading' => 'H', 'secondaryImage' => [...self::PHOTO, 'src' => 'javascript:x']])->errors());
    }

    public function test_social_feed_lists_profiles_and_posts_that_open_in_a_new_tab(): void
    {
        $posts = [
            ['href' => 'https://www.instagram.com/p/example-one/', 'image' => ['src' => '/images/brand/photos/valve.jpg', 'alt' => 'Valve on a water line', 'width' => 900, 'height' => 900]],
            ['href' => 'https://www.instagram.com/p/example-two/', 'image' => ['src' => '/images/brand/photos/drain.jpg', 'alt' => 'Sink drain', 'width' => 1200, 'height' => 800]],
            ['href' => 'javascript:alert(1)', 'image' => ['src' => '/images/brand/photos/drain.jpg', 'alt' => 'Unsafe', 'width' => 1200, 'height' => 800]],
        ];
        $profiles = [
            ['label' => 'INSTAGRAM', 'handle' => '@example.company', 'href' => 'https://www.instagram.com/example.company/'],
            ['label' => 'YOUTUBE', 'handle' => '@ExampleCompany'],
        ];

        $html = Blade::render('<x-blocks.social.feed heading="Connect with us on social media" :profiles="$profiles" :posts="$posts" />', compact('posts', 'profiles'));

        $this->assertSame(1, substr_count($html, '<h2'));
        $this->assertStringContainsString('aria-labelledby="social-title"', $html);
        $this->assertStringContainsString('INSTAGRAM', $html);
        $this->assertStringContainsString('@example.company', $html);
        $this->assertStringContainsString('@ExampleCompany', $html);
        $this->assertSame(2, substr_count($html, 'class="social-feed-post"'));
        $this->assertMatchesRegularExpression('#<a[^>]*href="https://www.instagram.com/p/example-one/"[^>]*target="_blank"[^>]*rel="noopener"#', $html);
        $this->assertStringContainsString('alt="Valve on a water line"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $registry = app(BlockRegistry::class);
        $valid = ['heading' => 'H', 'profiles' => $profiles, 'posts' => array_slice($posts, 0, 2)];

        $this->assertSame([], $registry->validate('social/feed', $valid)->errors());
        $this->assertArrayHasKey('/props/posts/2', $registry->validate('social/feed', [...$valid, 'posts' => $posts])->errors());
        $this->assertArrayHasKey('/props/posts/0', $registry->validate('social/feed', [...$valid, 'posts' => [['href' => '/relative/post', 'image' => $posts[0]['image']]]])->errors());
        $this->assertArrayHasKey('/props/posts', $registry->validate('social/feed', [...$valid, 'posts' => array_fill(0, 13, $posts[0])])->errors());
        $this->assertArrayHasKey('/props/profiles/0', $registry->validate('social/feed', [...$valid, 'profiles' => [['label' => 'INSTAGRAM']]])->errors());
    }

    public function test_social_profiles_shows_image_tiles_and_a_contact_card(): void
    {
        $tile = ['href' => 'https://example.com/profile', 'image' => ['src' => '/images/brand/pages/instagram.png', 'alt' => 'Example company on Instagram', 'width' => 400, 'height' => 403]];
        $card = ['label' => 'Click to Save contact info', 'href' => '/s/example.vcf', 'image' => ['src' => '/images/brand/pages/vcard-qr-3.png', 'alt' => 'QR code for the contact card', 'width' => 220, 'height' => 220]];

        $html = Blade::render('<x-blocks.social.profiles heading="Social Media" :profiles="$profiles" :card="$card" />', ['profiles' => [$tile, [...$tile, 'href' => 'javascript:alert(1)']], 'card' => $card]);

        $this->assertSame(1, substr_count($html, '<h2'));
        $this->assertSame(1, substr_count($html, 'class="social-profiles-tile"'));
        $this->assertMatchesRegularExpression('#<a[^>]*href="https://example.com/profile"[^>]*target="_blank"[^>]*rel="noopener"#', $html);
        $this->assertStringContainsString('alt="Example company on Instagram"', $html);
        $this->assertStringContainsString('href="/s/example.vcf"', $html);
        $this->assertStringContainsString('Click to Save contact info', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $registry = app(BlockRegistry::class);

        $this->assertSame([], $registry->validate('social/profiles', ['heading' => 'Social Media', 'profiles' => [$tile], 'card' => $card])->errors());
        $this->assertArrayHasKey('/props/profiles/0', $registry->validate('social/profiles', ['heading' => 'H', 'profiles' => [[...$tile, 'image' => null]]])->errors());
        $this->assertArrayHasKey('/props/card', $registry->validate('social/profiles', ['heading' => 'H', 'card' => [...$card, 'href' => 'javascript:x']])->errors());
    }

    public function test_a_link_may_point_at_a_document_shipped_in_public(): void
    {
        $links = app(PublicLinkTarget::class);

        $this->assertTrue($links->exists('/s/JW.vcf'));
        $this->assertFalse($links->exists('/s/missing.vcf'));
        $this->assertFalse($links->exists('/index.php'));
        $this->assertFalse($links->exists('/s/../index.php'));
    }

    public function test_article_hero_shows_dates_the_way_people_read_them(): void
    {
        $html = Blade::render('<x-blocks.hero.article heading="Transform Your Bathroom" author="Ali Wright" published-at="2023-09-08" updated-at="2023-10-01" />');

        $this->assertStringContainsString('<time datetime="2023-09-08">Sep 8, 2023</time>', $html);
        $this->assertStringContainsString('<time datetime="2023-10-01">Updated Oct 1, 2023</time>', $html);
        $this->assertStringNotContainsString('>2023-09-08<', $html);
        $this->assertStringNotContainsString('Published 2023', $html);
        $this->assertStringContainsString('Ali Wright', $html);
    }

    public function test_team_spotlight_renders_each_member_beside_their_name(): void
    {
        $members = [
            ['name' => 'Jordan Example', 'role' => 'Sample role', 'bio' => 'Fictional bio one.', 'image' => ['src' => '/images/home/technician-controls.jpg', 'alt' => 'Fictional team member', 'width' => 1600, 'height' => 2844]],
            ['name' => 'Taylor Example', 'role' => 'Sample role', 'image' => ['src' => '/images/home/technician-detail.jpg', 'alt' => 'Fictional team member', 'width' => 1600, 'height' => 2845]],
            ['name' => 'Missing photo', 'role' => 'Sample role'],
        ];

        $html = Blade::render('<x-team-spotlight heading="Meet the team" :members="$members" />', ['members' => $members]);

        $this->assertStringContainsString('class="team-spotlight"', $html);
        $this->assertMatchesRegularExpression('#<h2 id="team-spotlight-title"[^>]*>Meet the team</h2>#', $html);
        $this->assertSame(2, substr_count($html, '<h3'));
        $this->assertSame(2, substr_count($html, 'class="team-spotlight-photo"'));
        $this->assertStringContainsString('Fictional bio one.', $html);
        $this->assertStringContainsString('width="1600"', $html);
        $this->assertStringContainsString('height="2844"', $html);
        $this->assertStringNotContainsString('Missing photo', $html);

        $this->assertSame('', trim(Blade::render('<x-team-spotlight heading="Meet the team" :members="[]" />')));
    }

    private function tag(string $html, string $name): string
    {
        preg_match('/<'.$name.'\b[^>]*>/s', $html, $match);

        return $match[0] ?? '';
    }
}
