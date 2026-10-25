<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TeamGridBlockTest extends TestCase
{
    public function test_it_renders_complete_team_members_and_an_optional_linkedin_profile(): void
    {
        $html = Blade::render(
            <<<'BLADE'
                <x-blocks.team.grid
                    id="service-team"
                    eyebrow="People who care"
                    heading="Meet our indoor air team"
                    body="A synthetic template roster."
                    :members="$members"
                />
            BLADE,
            ['members' => [
                [
                    'name' => 'Example Person One',
                    'role' => 'Service Manager',
                    'image' => [
                        'src' => 'images/home/family-relaxing.jpg',
                        'alt' => 'Synthetic template portrait one',
                        'width' => 1600,
                        'height' => 2400,
                    ],
                    'linkedin_url' => 'https://www.linkedin.com/',
                ],
                [
                    'name' => 'Example Person Two',
                    'role' => 'Lead Technician',
                    'image' => [
                        'src' => 'images/home/technician-detail.jpg',
                        'alt' => 'Synthetic template portrait two',
                        'width' => 1600,
                        'height' => 2845,
                    ],
                ],
            ]],
        );

        $this->assertStringContainsString('id="service-team"', $html);
        $this->assertStringContainsString('aria-labelledby="service-team-title"', $html);
        $this->assertStringContainsString('data-team-variant="grid"', $html);
        $this->assertStringContainsString('People who care', $html);
        $this->assertStringContainsString('Meet our indoor air team', $html);
        $this->assertStringContainsString('A synthetic template roster.', $html);
        $this->assertSame(2, substr_count($html, 'class="team-card"'));
        $this->assertStringContainsString('Example Person One', $html);
        $this->assertStringContainsString('Service Manager', $html);
        $this->assertStringContainsString('Example Person Two', $html);
        $this->assertStringContainsString('Lead Technician', $html);
        $this->assertStringContainsString('src="http://localhost/images/home/family-relaxing.jpg"', $html);
        $this->assertStringContainsString('width="1600"', $html);
        $this->assertStringContainsString('height="2400"', $html);
        $this->assertStringContainsString('height="2845"', $html);
        $this->assertSame(2, substr_count($html, 'loading="lazy"'));
        $this->assertStringContainsString('href="https://www.linkedin.com/"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('aria-label="View Example Person One on LinkedIn"', $html);
        $this->assertSame(1, substr_count($html, 'class="team-card-link"'));
        $this->assertStringContainsString('>in</span>', $html);
    }

    public function test_public_content_is_escaped_and_unsafe_profile_schemes_are_omitted(): void
    {
        $html = Blade::render(
            '<x-blocks.team.grid :eyebrow="$eyebrow" :heading="$heading" :body="$body" :members="$members" />',
            [
                'eyebrow' => '<People>',
                'heading' => '<script>alert("team")</script>',
                'body' => '<strong>Roster</strong>',
                'members' => [[
                    'name' => '<Example Person>',
                    'role' => '<Manager>',
                    'image' => [
                        'src' => 'images/home/family-relaxing.jpg',
                        'alt' => '<Synthetic portrait>',
                        'width' => 1600,
                        'height' => 2400,
                    ],
                    'linkedin_url' => 'javascript:alert("unsafe")',
                ]],
            ],
        );

        $this->assertStringContainsString('&lt;People&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;team&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;strong&gt;Roster&lt;/strong&gt;', $html);
        $this->assertStringContainsString('&lt;Example Person&gt;', $html);
        $this->assertStringContainsString('&lt;Manager&gt;', $html);
        $this->assertStringContainsString('alt="&lt;Synthetic portrait&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<strong>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('team-card-link', $html);
    }

    public function test_incomplete_member_records_are_omitted(): void
    {
        $html = Blade::render(
            '<x-blocks.team.grid heading="Meet the team" :members="$members" />',
            ['members' => [
                [
                    'name' => 'Complete Person',
                    'role' => 'Technician',
                    'image' => [
                        'src' => 'images/home/technician-controls.jpg',
                        'alt' => 'Synthetic technician portrait',
                        'width' => 1600,
                        'height' => 2844,
                    ],
                ],
                [
                    'name' => 'Missing Role',
                    'image' => [
                        'src' => 'images/home/family-homework.jpg',
                        'alt' => 'Synthetic portrait',
                        'width' => 1600,
                        'height' => 1067,
                    ],
                ],
                [
                    'name' => 'Broken Image',
                    'role' => 'Coordinator',
                    'image' => [
                        'src' => 'images/home/family-homework.jpg',
                        'alt' => 'Synthetic portrait',
                        'width' => 0,
                        'height' => 1067,
                    ],
                ],
            ]],
        );

        $this->assertSame(1, substr_count($html, 'class="team-card"'));
        $this->assertStringContainsString('Complete Person', $html);
        $this->assertStringNotContainsString('Missing Role', $html);
        $this->assertStringNotContainsString('Broken Image', $html);
    }

    public function test_empty_valid_member_collection_omits_the_grid_cleanly(): void
    {
        $html = Blade::render(
            '<x-blocks.team.grid heading="Meet the team" :members="$members" />',
            ['members' => 'not-an-array'],
        );

        $this->assertStringContainsString('Meet the team', $html);
        $this->assertStringContainsString('is-empty', $html);
        $this->assertStringNotContainsString('class="team-grid"', $html);
        $this->assertStringNotContainsString('class="team-card"', $html);
    }
}
