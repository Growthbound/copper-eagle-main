<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The shipped pages carry what the live Squarespace site shows: team bios and
 * the footer team spotlight, the header videos, the contact page's social
 * feed and contact details, the social media tiles and vCard, the closing
 * photos, the buttonless guarantee bands, and the blog wording.
 */
class LiveParityPagesTest extends TestCase
{
    private const BIO_OPENINGS = [
        'Matt Porter' => 'Matt Porter is our trusted Foreman in the field and an exceptional Master Plumber.',
        'Dalton' => 'Dalton is one of our outstanding Journeyman Plumbers who brings skill, honesty, and a positive attitude to every job.',
        'Holden' => 'Holden is one of our rising soon to be Journeyman Plumbers and a key part of our growing team.',
        'Spencer' => 'Spencer is the longest-standing employee at Copper Eagle Plumbing and has been a vital part of our journey through every stage of growth.',
        'JD' => 'JD is the newest Plumbing Apprentice at Copper Eagle Plumbing, and he has already shown the dedication and determination it takes to succeed in the trade.',
        'Matthew "Hibs"' => 'Last but certainly not least, Matthew "Hibs" serves as our Customer Retention Agent and Office Manager.',
    ];

    /** The live contact page's Instagram feed, in order. */
    private const INSTAGRAM_POSTS = [
        'https://www.instagram.com/p/DBktXGdxyVt/',
        'https://www.instagram.com/p/C8gCIPzRJel/',
        'https://www.instagram.com/p/C8QdjnPvXPK/',
        'https://www.instagram.com/p/C8Qc5QhvMQu/',
        'https://www.instagram.com/p/C2LubLixsQN/',
        'https://www.instagram.com/reel/CpJ4BFyoo64/',
        'https://www.instagram.com/p/CpBwf-kLIeq/',
        'https://www.instagram.com/p/CoOlD6ZumFt/',
        'https://www.instagram.com/p/CoOht2vrhbh/',
        'https://www.instagram.com/p/CoOhIHvrY9p/',
    ];

    private const SERVICE_PAGES = [
        'general-plumbing-services-salt-lake-utah',
        'drain-cleaning-repair-master-plumber-utah',
        'water-filtration-utah-hard-water-softeners',
        'water-heater-repairs-installation-utah',
        'steam-shower-install-repair-salt-lake-city-utah',
        'new-construction-remodels-plumber-utah',
        'gas-lines-utah',
        'garbage-disposals-appliances-plumbing-utah',
    ];

    public function test_every_team_member_carries_their_bio(): void
    {
        $members = $this->page('team')['payload']['sections']['team']['members'];

        $this->assertSame(array_keys(self::BIO_OPENINGS), array_column($members, 'name'));

        foreach ($members as $member) {
            $this->assertStringStartsWith(self::BIO_OPENINGS[$member['name']], $member['bio'] ?? '', $member['name']);
        }

        $this->assertStringEndsWith('every customer receives the exceptional experience they deserve.', $members[5]['bio']);
    }

    public function test_the_footer_spotlights_the_team_on_every_page_from_the_team_page(): void
    {
        foreach (['/', '/contact-copper-eagle-plumbing', '/gas-lines-utah', '/blog'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $spotlight = strpos($html, 'class="team-spotlight"');

            $this->assertNotFalse($spotlight, "{$path} has no team spotlight.");
            $this->assertLessThan(strpos($html, '<footer'), $spotlight, "{$path}: the spotlight sits above the footer.");
            $this->assertMatchesRegularExpression('#<h2 id="team-spotlight-title"[^>]*>Meet the Copper Eagle Team</h2>#', $html);

            foreach (self::BIO_OPENINGS as $name => $opening) {
                $this->assertStringContainsString('<h3>'.e($name).'</h3>', $html, "{$path}: {$name}");
                $this->assertStringContainsString(e($opening), $html, "{$path}: {$name}");
            }

            $this->assertStringContainsString('src="/images/brand/photos/team-truck-5.jpg"', $html);
        }

        // The team page shows the same people in its grid, so it does not repeat them.
        $team = $this->get('/team')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="team-spotlight"', $team);
        $this->assertSame(6, substr_count($team, 'class="team-card-bio"'));

        // Staff photos never reach a fictional template preview.
        $this->assertStringNotContainsString('team-truck', $this->get('/_templates/home-page')->assertOk()->getContent());
    }

    public function test_home_services_and_team_headers_play_their_videos(): void
    {
        foreach ([
            '/' => 'hero-home',
            '/plumbing-services-salt-lake-city-utah' => 'hero-services',
            '/team' => 'hero-team',
        ] as $path => $video) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('data-src="/videos/brand/'.$video.'.mp4"', $html, $path);
            $this->assertStringContainsString('poster="/videos/brand/'.$video.'-poster.jpg"', $html, $path);
            $this->assertFileExists(public_path('videos/brand/'.$video.'.mp4'));
            $this->assertSame([1280, 720], array_slice(getimagesize(public_path('videos/brand/'.$video.'-poster.jpg')), 0, 2));
            $this->assertSame(1, substr_count($html, '<h1'), $path);
        }
    }

    public function test_contact_page_shows_the_social_feed_contact_details_and_crew_photo(): void
    {
        $html = $this->get('/contact-copper-eagle-plumbing')->assertOk()->getContent();

        $this->assertStringContainsString('Connect with us on social media', $html);
        $this->assertStringContainsString('@Copper.Eagle.Plumbing', $html);
        $this->assertStringContainsString('@CopperEaglePlumbing', $html);
        $this->assertSame(10, substr_count($html, 'class="social-feed-post"'));

        foreach (self::INSTAGRAM_POSTS as $index => $post) {
            $this->assertMatchesRegularExpression('#<a[^>]*href="'.preg_quote($post, '#').'"[^>]*target="_blank"[^>]*rel="noopener"#', $html, $post);
            $image = sprintf('images/brand/instagram/instagram-%02d.jpg', $index + 1);
            $this->assertStringContainsString('src="/'.$image.'"', $html);
            $this->assertFileExists(public_path($image));
        }

        // Phone and email come from site settings, not page content.
        $this->assertStringContainsString('class="call-link button button-orange contact-split-call"', $html);
        $this->assertStringContainsString('href="mailto:'.config('site.email').'"', $html);
        $this->assertStringNotContainsString('mailto:', (string) file_get_contents(base_path('content/managed/contact-copper-eagle-plumbing.json')));

        $this->assertStringContainsString('src="/images/brand/pages/crew-photo.jpg"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_social_media_page_shows_the_profile_tiles_and_the_vcard(): void
    {
        $html = $this->get('/socialmedia')->assertOk()->getContent();

        foreach ([
            'https://nextdoor.com/page/copper-eagle-plumbing-glenburn-me' => 'next-door.png',
            'https://www.tiktok.com/t/ZP8P2soup/' => 'tictok.png',
            'https://g.co/kgs/Rxtokp' => 'google-reviews.png',
            'https://www.instagram.com/copper.eagle.plumbing/' => 'instagram.png',
            'https://www.facebook.com/coppereagleplumbingut' => 'face-book.png',
        ] as $href => $file) {
            $this->assertMatchesRegularExpression('#<a[^>]*href="'.preg_quote($href, '#').'"[^>]*target="_blank"#', $html, $href);
            $this->assertStringContainsString('src="/images/brand/pages/'.$file.'"', $html);
            $this->assertLessThan(60_000, filesize(public_path('images/brand/pages/'.$file)), "{$file} is not compressed.");
        }

        $this->assertStringContainsString('href="/s/JW.vcf"', $html);
        $this->assertStringContainsString('src="/images/brand/pages/vcard-qr-3.png"', $html);
        $this->assertStringContainsString('Click to Save contact info', $html);
        $this->assertStringContainsString('BEGIN:VCARD', (string) file_get_contents(public_path('s/JW.vcf')));
    }

    public function test_closing_bands_and_the_review_panel_carry_the_live_photos(): void
    {
        $this->get('/general-plumbing-services-salt-lake-utah')
            ->assertOk()
            ->assertSee('class="inline-cta-media"', false)
            ->assertSee('src="/images/brand/pages/dsc-0837.jpg"', false);

        $reviews = $this->get('/reviews-copper-eagle-plumbing')->assertOk()->getContent();

        $this->assertStringContainsString('class="cta-panel-logo"', $reviews);
        $this->assertStringContainsString('src="/images/brand/pages/google-g-icon-download.png"', $reviews);
        $this->assertStringContainsString('src="/images/brand/pages/jordan-the-plumber-utah.jpg"', $reviews);
        $this->assertLessThan(strpos($reviews, 'jordan-the-plumber-utah.jpg'), strpos($reviews, 'google-g-icon-download.png'));
    }

    public function test_service_guarantee_bands_have_no_button_like_the_live_site(): void
    {
        foreach (self::SERVICE_PAGES as $slug) {
            $this->assertArrayNotHasKey('cta', $this->page($slug)['payload']['sections']['guarantee'], $slug);

            $html = $this->get('/'.$slug)->assertOk()->getContent();
            preg_match('#data-page-section="guarantee">(.*?)data-page-section="faq"#s', $html, $guarantee);

            $this->assertNotEmpty($guarantee, $slug);
            $this->assertStringNotContainsString('class="button', $guarantee[1], $slug);
        }
    }

    public function test_blog_index_uses_the_live_wording_and_readable_dates(): void
    {
        $html = $this->get('/blog')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>Blog</h1>#', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(2, substr_count($html, '>Read More</a>'));
        $this->assertStringContainsString('<time datetime="2026-06-30">6/30/26</time>', $html);
        $this->assertStringContainsString('<time datetime="2023-09-08">9/8/23</time>', $html);
        $this->assertStringContainsString('Team Implementation', $html);
        $this->assertStringContainsString('Ali Wright', $html);
        $this->assertStringContainsString('src="/images/brand/pages/r-architecture-rfxkg1qm1ri-unsplash.jpg"', $html);

        foreach (['Learning center', 'Learning Center', 'Articles and guides', '>Guide<', 'Read guide'] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $html);
        }

        $this->get('/blog/plumbing-remodel-bathroom-salt-lake-city-utah')
            ->assertOk()
            ->assertSee('<time datetime="2023-09-08">Sep 8, 2023</time>', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $slug): array
    {
        return json_decode((string) file_get_contents(base_path("content/managed/{$slug}.json")), true, flags: JSON_THROW_ON_ERROR);
    }
}
